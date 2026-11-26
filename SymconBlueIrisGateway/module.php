<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/DataMessage.php';
require_once __DIR__ . '/../libs/TlsTrust.php';

use BlueIris\DataMessage;
use BlueIris\TlsTrust;

class BlueIrisGateway extends IPSModule
{
    private const MAX_CERTIFICATE_FILE_SIZE = 64 * 1024;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('IPAddress', '192.168.178.1');
        $this->RegisterPropertyInteger('Port', 81);
        $this->RegisterPropertyString('Protocol', 'http');
        $this->RegisterPropertyString('TlsCertificate', '');
        $this->RegisterPropertyInteger('Timeout', 3);
        $this->RegisterPropertyInteger('Interval', 10);
        $this->RegisterPropertyString('Username', 'admin');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyBoolean('Debug', false);

        $this->RegisterTimer('SyncData', 0, 'BlueIrisGateway_SyncData($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetBuffer('BlueIrisSession', '');
        if ($this->GetBaseURL() === null) {
            $this->SetTimerInterval('SyncData', 0);
            return;
        }
        if (!TlsTrust::isValid($this->GetTlsCertificate())) {
            $this->Fail(206, 'Das hinterlegte PEM-Zertifikat ist ungültig oder wird von der PHP-Laufzeit nicht unterstützt.');
            $this->SetTimerInterval('SyncData', 0);
            return;
        }

        $this->SetStatus(102);
        $this->SetTimerInterval('SyncData', max(0, $this->ReadPropertyInteger('Interval')) * 1000);
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        $certificate = trim($this->ReadPropertyString('TlsCertificate'));
        if ($certificate !== '') {
            foreach ($form['elements'] as $index => $field) {
                if (($field['name'] ?? '') === 'TlsCertificateStatus') {
                    $form['elements'][$index]['caption'] = TlsTrust::isValid($certificate)
                        ? 'PEM certificate stored for HTTPS'
                        : 'Stored PEM certificate is invalid or unsupported';
                    break;
                }
            }
        }
        return json_encode($form, JSON_THROW_ON_ERROR);
    }

    /** Imports the base64 payload from SelectFile and returns the popup's error message. */
    public function ImportTlsCertificate(string $file): string
    {
        if ($file === '') {
            return $this->Translate('Select a public PEM certificate file.');
        }
        if (strlen($file) > 4 * intdiv(self::MAX_CERTIFICATE_FILE_SIZE + 2, 3)) {
            return $this->Translate('The certificate file exceeds 64 KiB.');
        }
        $decoded = base64_decode($file, true);
        if ($decoded === false) {
            return $this->Translate('The file must contain only public PEM certificates supported by this PHP runtime.');
        }
        if (strlen($decoded) > self::MAX_CERTIFICATE_FILE_SIZE) {
            return $this->Translate('The certificate file exceeds 64 KiB.');
        }
        if (strncmp($decoded, "\xEF\xBB\xBF", 3) === 0) {
            $decoded = substr($decoded, 3);
        }
        $certificate = trim($decoded);
        if ($certificate === '' || !TlsTrust::isValid($certificate)) {
            return $this->Translate('The file must contain only public PEM certificates supported by this PHP runtime.');
        }
        if (IPS_HasChanges($this->InstanceID)) {
            return $this->Translate('Apply or discard pending gateway changes before importing.');
        }

        $previous = $this->ReadPropertyString('TlsCertificate');
        if ($certificate === $previous) {
            return '';
        }
        if (IPS_SetProperty($this->InstanceID, 'TlsCertificate', $certificate) === false) {
            return $this->Translate('The PEM certificate could not be saved.');
        }
        if (IPS_ApplyChanges($this->InstanceID) === false) {
            IPS_SetProperty($this->InstanceID, 'TlsCertificate', $previous);
            return $this->Translate('The PEM certificate could not be saved.');
        }

        $this->ReloadForm();
        return '';
    }

    public function ForwardData($JSONString)
    {
        $message = DataMessage::decode(
            $JSONString,
            DataMessage::CAMERA_REQUEST,
            DataMessage::SYSTEM_REQUEST,
            DataMessage::CONFIGURATOR_REQUEST
        );
        $data = $message['data'] ?? [];
        if (!is_array($data)) {
            return null;
        }

        switch ($message['cmd'] ?? null) {
            case 'GetLink':
                $certificate = $this->GetTlsCertificate();
                $link = [
                    'link' => $this->GetBaseURL(),
                    'user' => $this->ReadPropertyString('Username'),
                    'pw'   => $this->ReadPropertyString('Password')
                ];
                if (!TlsTrust::isValid($certificate)) {
                    $link['link'] = null;
                } elseif ($certificate !== '' && $link['link'] !== null) {
                    $link['tlsCertificate'] = $certificate;
                }
                return json_encode($link);
            case 'CamConfig':
                return $this->SendCommand('camconfig', $data);
            case 'AlertList':
                return $this->SendCommand('alertlist', $data);
            case 'ClipList':
                return $this->SendCommand('cliplist', $data);
            case 'PTZ':
                return $this->SendCommand('ptz', $data);
            case 'Trigger':
                return $this->SendCommand('trigger', $data);
            case 'Status':
                return $this->SendCommand('status', $data);
            case 'Log':
                return $this->SendCommand('log', $data);
            case 'SysConfig':
                return $this->SendCommand('sysconfig', $data);
        }

        return null;
    }

    public function SendJSONData(string $cmd, string $json_string)
    {
        $session = $this->Login();
        if ($session === 'ERROR') {
            return null;
        }

        $data = json_decode($json_string, true);
        $response = $this->Request($cmd, is_array($data) ? $data : [], $session);
        return $response['raw'] ?? '{}';
    }

    public function Login()
    {
        $session = $this->GetBuffer('BlueIrisSession');
        if ($session !== '') {
            $response = $this->Authenticate($session);
            if ($response === null) {
                return 'ERROR';
            }
            if ($response['result'] === 'success') {
                return $this->SaveSession($response);
            }
            $this->SetBuffer('BlueIrisSession', '');
        }

        $challenge = $this->Request('login');
        if ($challenge === null) {
            return 'ERROR';
        }
        $session = $challenge['body']['session'] ?? null;
        if (!is_string($session) || $session === '') {
            $this->Fail(203, 'Blue Iris hat keine Sitzungskennung geliefert.');
            return 'ERROR';
        }

        $response = $this->Authenticate($session);
        if ($response === null) {
            return 'ERROR';
        }
        if ($response['result'] !== 'success') {
            $this->Fail(205, 'Blue Iris hat die Anmeldung abgelehnt.');
            return 'ERROR';
        }

        return $this->SaveSession($response);
    }

    public function Logout(?string $session = null)
    {
        if ($session === null) {
            $this->Fail(203, 'Abmeldung ohne Sitzungskennung.');
            return 'ERROR';
        }

        $response = $this->Request('logout', [], $session);
        if ($response === null) {
            return 'ERROR';
        }
        if ($response['body']['result'] !== 'success') {
            return false;
        }

        if ($session === $this->GetBuffer('BlueIrisSession')) {
            $this->SetBuffer('BlueIrisSession', '');
        }

        return true;
    }

    public function CamList(?string $session = null)
    {
        if ($session === null) {
            $this->Fail(203, 'Kameraliste ohne Sitzungskennung angefordert.');
            return 'ERROR';
        }

        return $this->ReadCameraList($session) ?? [];
    }

    public function SyncData()
    {
        $session = $this->Login();
        if ($session === 'ERROR') {
            return;
        }

        $cameras = $this->ReadCameraList($session);
        if ($cameras !== null) {
            $configuratorCameras = [];
            foreach ($cameras as $camera) {
                $shortName = $camera['optionValue'] ?? null;
                if (!is_string($shortName) || $shortName === '') {
                    continue;
                }

                $configuratorCameras[] = [
                    'name'      => $camera['optionDisplay'] ?? $shortName,
                    'shortName' => $shortName,
                    'ptz'       => (bool) ($camera['ptz'] ?? false)
                ];
                $this->SendDataToChildren(DataMessage::encode(DataMessage::CAMERA_UPDATE, [
                    'sname'   => $shortName,
                    'cmd'     => 'CamList',
                    'payload' => $camera
                ]));
            }

            $this->SendDataToChildren(DataMessage::encode(DataMessage::CONFIGURATOR_UPDATE, [
                'cmd'     => 'CamList',
                'payload' => $configuratorCameras
            ]));
        }

        $status = $this->Request('status', [], $session);
        if (($status['body']['result'] ?? null) === 'success' && is_array($status['body']['data'] ?? null)) {
            $this->SendDataToChildren(DataMessage::encode(DataMessage::SYSTEM_UPDATE, [
                'cmd'     => 'Status',
                'payload' => $status['body']['data']
            ]));
        }
    }

    private function ReadCameraList(string $session): ?array
    {
        $response = $this->Request('camlist', [], $session);
        if (($response['body']['result'] ?? null) === 'success' && is_array($response['body']['data'] ?? null)) {
            return $response['body']['data'];
        }

        if ($response !== null) {
            $this->Fail(203, 'Blue Iris hat keine gültige Kameraliste geliefert.');
        }
        return null;
    }

    private function Authenticate(string $session): ?array
    {
        $username = $this->ReadPropertyString('Username');
        $password = $this->ReadPropertyString('Password');

        // Preserve the authentication behavior of existing installations.
        $credentials = preg_replace('/[^A-Za-z0-9. :-]/', '', "$username:$session:$password");
        $response = $this->Request('login', ['response' => md5($credentials)], $session);
        return $response['body'] ?? null;
    }

    private function SaveSession(array $response): string
    {
        $session = $response['session'] ?? null;
        if (!is_string($session) || $session === '') {
            $this->SetBuffer('BlueIrisSession', '');
            $this->Fail(203, 'Die Anmeldung lieferte keine gültige Sitzungskennung.');
            return 'ERROR';
        }

        $this->SetBuffer('BlueIrisSession', $session);
        $this->SetStatus(102);
        return $session;
    }

    private function SendCommand(string $command, array $data): string
    {
        $session = $this->Login();
        if ($session === 'ERROR') {
            return '{}';
        }

        $body = $this->Request($command, $data, $session)['body'] ?? null;
        if (($body['result'] ?? null) !== 'success') {
            return '{}';
        }

        if ($command === 'ptz' || $command === 'trigger') {
            return 'success';
        }

        return array_key_exists('data', $body) ? json_encode($body['data'], JSON_THROW_ON_ERROR) : '{}';
    }

    private function GetBaseURL(): ?string
    {
        $protocol = $this->ReadPropertyString('Protocol');
        $port = $this->ReadPropertyInteger('Port');
        if (!in_array($protocol, ['http', 'https'], true) || $port < 1 || $port > 65535) {
            $this->Fail(201, 'Protokoll oder Port ist ungültig.');
            return null;
        }

        $host = $this->ReadPropertyString('IPAddress');
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $host = '[' . $host . ']';
        }

        return $protocol . '://' . $host . ':' . $port;
    }

    private function Request(string $command, array $data = [], ?string $session = null): ?array
    {
        $request = ['cmd' => $command];
        if ($session !== null) {
            $request['session'] = $session;
        }
        unset($data['session']);

        if (!function_exists('curl_init')) {
            $this->Fail(203, 'Die PHP-Erweiterung cURL ist nicht verfügbar.');
            return null;
        }

        $baseURL = $this->GetBaseURL();
        if ($baseURL === null) {
            return null;
        }

        $timeout = max(1, $this->ReadPropertyInteger('Timeout'));
        $curl = curl_init($baseURL . '/json');
        if ($curl === false) {
            $this->Fail(203, 'Die Blue-Iris-Verbindung konnte nicht initialisiert werden.');
            return null;
        }

        $options = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($request + $data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json']
        ];
        if ($session !== null) {
            $options[CURLOPT_COOKIE] = 'PHPSESSID=' . $session;
        }
        curl_setopt_array($curl, $options);
        if (!TlsTrust::configure($curl, $this->GetTlsCertificate())) {
            $this->Fail(206, 'Das hinterlegte PEM-Zertifikat konnte nicht für die HTTPS-Verbindung aktiviert werden.');
            return null;
        }

        $raw = curl_exec($curl);
        if ($raw === false) {
            $transportError = curl_errno($curl);
            if (in_array($transportError, [CURLE_SSL_CACERT, CURLE_SSL_CACERT_BADFILE, CURLE_SSL_CERTPROBLEM], true)) {
                $this->Fail(206, 'Das HTTPS-Zertifikat von Blue Iris konnte nicht geprüft werden.');
            } elseif ($transportError === CURLE_SSL_CONNECT_ERROR) {
                $this->Fail(206, 'Die TLS-Verbindung zu Blue Iris konnte nicht aufgebaut werden.');
            } else {
                $this->Fail(in_array($transportError, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT], true) ? 204 : 203, 'Blue Iris ist nicht erreichbar.');
            }
            return null;
        }

        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpStatus < 200 || $httpStatus >= 300) {
            $this->Fail(in_array($httpStatus, [401, 403], true) ? 205 : 203, 'Blue Iris meldet HTTP-Status ' . $httpStatus . '.');
            return null;
        }

        $body = json_decode($raw, true);
        if (!is_array($body) || !in_array($body['result'] ?? null, ['success', 'fail'], true)) {
            $this->Fail(203, 'Blue Iris hat keine gültige JSON-Antwort geliefert.');
            return null;
        }

        return ['raw' => $raw, 'body' => $body];
    }

    private function GetTlsCertificate(): string
    {
        return $this->ReadPropertyString('Protocol') === 'https' ? trim($this->ReadPropertyString('TlsCertificate')) : '';
    }

    private function Fail(int $status, string $message): void
    {
        $this->SetStatus($status);
        if ($this->ReadPropertyBoolean('Debug')) {
            $this->SendDebug('BlueIris Gateway', $message, 0);
        }
    }
}
