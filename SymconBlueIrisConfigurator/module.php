<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/DataMessage.php';

use BlueIris\DataMessage;

class SymconBlueIrisConfigurator extends IPSModule
{
    private const CAMERA_MODULE = '{5308D185-A3D2-42D0-B6CE-E9D3080CE184}';
    private const SYSTEM_MODULE = '{CDFC7E83-C425-E923-9B6F-ECB22F3DFCC9}';

    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{E138AFDC-D1E0-B462-A5E5-AF24F57D4686}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        $gatewayID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $system = [
            'id'        => 1,
            'name'      => 'BlueIris System',
            'shortName' => '',
            'ptz'       => ''
        ];
        if ($gatewayID > 0) {
            $system['create'] = [
                'moduleID'      => self::SYSTEM_MODULE,
                'configuration' => ['GetLog' => false]
            ];
            foreach (IPS_GetInstanceListByModuleID(self::SYSTEM_MODULE) as $id) {
                if (IPS_GetInstance($id)['ConnectionID'] === $gatewayID) {
                    $system['instanceID'] = $id;
                    break;
                }
            }
        }

        $values = [
            $system,
            ['id' => 2, 'name' => $this->Translate('Cameras'), 'shortName' => '', 'ptz' => '']
        ];
        foreach ($this->GetCameras($gatewayID) as $camera) {
            $camera['parent'] = 2;
            $camera['create'] = [
                'moduleID'      => self::CAMERA_MODULE,
                'configuration' => ['ShortName' => $camera['shortName'], 'PTZ' => $camera['ptz']]
            ];
            $values[] = $camera;
        }

        $form['elements'][0]['values'] = $values;
        return json_encode($form, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function ReceiveData($JSONString)
    {
        $message = DataMessage::decode($JSONString, DataMessage::CONFIGURATOR_UPDATE);
        if (($message['cmd'] ?? null) !== 'CamList' || !is_array($message['payload'] ?? null)) {
            return;
        }

        $gatewayID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $this->SetBuffer('Discovery', json_encode([
            'gatewayID' => $gatewayID,
            'cameras'   => $message['payload']
        ], JSON_THROW_ON_ERROR));
    }

    private function GetCameras(int $gatewayID): array
    {
        if ($gatewayID <= 0) {
            return [];
        }

        $cameras = [];
        $known = json_decode($this->GetBuffer('Discovery'), true);
        if (($known['gatewayID'] ?? null) === $gatewayID) {
            foreach ($known['cameras'] as $camera) {
                $shortName = $camera['shortName'] ?? null;
                if (!is_string($shortName) || $shortName === '') {
                    continue;
                }

                $cameras[$shortName] = [
                    'name'       => is_string($camera['name'] ?? null) ? $camera['name'] : $shortName,
                    'shortName'  => $shortName,
                    'ptz'        => (bool) ($camera['ptz'] ?? false),
                    'instanceID' => 0
                ];
            }
        }

        foreach (IPS_GetInstanceListByModuleID(self::CAMERA_MODULE) as $id) {
            if (IPS_GetInstance($id)['ConnectionID'] !== $gatewayID) {
                continue;
            }

            $shortName = IPS_GetProperty($id, 'ShortName');
            if ($shortName === '') {
                continue;
            }

            $cameras[$shortName] = ($cameras[$shortName] ?? [
                'name'      => $shortName,
                'shortName' => $shortName,
                'ptz'       => IPS_GetProperty($id, 'PTZ')
            ]);
            if (empty($cameras[$shortName]['instanceID'])) {
                $cameras[$shortName]['instanceID'] = $id;
            }
        }

        return array_values($cameras);
    }
}
