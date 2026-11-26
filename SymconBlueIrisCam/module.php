<?php

    require_once __DIR__ . '/../libs/DataMessage.php';

    use BlueIris\DataMessage;

    // Klassendefinition
    class SymconBlueIrisCam extends IPSModule {
        public function __construct(int $InstanceID) {
            parent::__construct($InstanceID);
        }

        public function Create() {
            parent::Create();

            $this->ConnectParent("{E138AFDC-D1E0-B462-A5E5-AF24F57D4686}");

            // Modul-Eigenschaftserstellung
            $this->RegisterPropertyString("ShortName", "");
            $this->RegisterPropertyBoolean("PTZ", false);
            $this->RegisterPropertyBoolean("showFPS", false);
            $this->RegisterPropertyBoolean("Debug", false);
        }
        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();
            $this->SetReceiveDataFilter('');

            $this->RegisterVariableBoolean("isTriggered", $this->Translate("Triggered"), "~Switch", 3);
            $this->RegisterVariableBoolean("isMotion", $this->Translate("Motion"), "~Switch", 2);

            //create variables
            if (!$this->IsIndexCamera()) {
                $this->RegisterVariableBoolean("isAlerting", $this->Translate("Alert"), "~Switch", 1);
                $this->RegisterVariableBoolean("isRecording", $this->Translate("Recording"), "~Switch", 4);
                $this->RegisterVariableBoolean("isOnline", $this->Translate("Online"), "~Switch", 90);
                $this->RegisterVariableBoolean("isEnabled", $this->Translate("Enabled"), "~Switch", 90);
                $this->RegisterVariableBoolean("isPaused", $this->Translate("Pause"), "~Switch", 91);
                $this->RegisterVariableBoolean("isNoSignal", $this->Translate("NoSignal"), "~Switch", 92);

                $this->EnableAction("isEnabled");
                $this->EnableAction("isPaused");
                $this->EnableAction("isTriggered");

                if ($this->ReadPropertyBoolean("PTZ")) {
                    if (!IPS_VariableProfileExists("BlueIris.Preset")) {
                        IPS_CreateVariableProfile("BlueIris.Preset", 1);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 101, $this->Translate("PTZ-Preset") . " 1", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 102, $this->Translate("PTZ-Preset") . " 2", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 103, $this->Translate("PTZ-Preset") . " 3", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 104, $this->Translate("PTZ-Preset") . " 4", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 105, $this->Translate("PTZ-Preset") . " 5", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 106, $this->Translate("PTZ-Preset") . " 6", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 107, $this->Translate("PTZ-Preset") . " 7", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 108, $this->Translate("PTZ-Preset") . " 8", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 109, $this->Translate("PTZ-Preset") . " 9", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 110, $this->Translate("PTZ-Preset") . " 10", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 111, $this->Translate("PTZ-Preset") . " 11", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 112, $this->Translate("PTZ-Preset") . " 12", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 113, $this->Translate("PTZ-Preset") . " 13", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 114, $this->Translate("PTZ-Preset") . " 14", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 115, $this->Translate("PTZ-Preset") . " 15", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 116, $this->Translate("PTZ-Preset") . " 16", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 117, $this->Translate("PTZ-Preset") . " 17", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 118, $this->Translate("PTZ-Preset") . " 18", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 119, $this->Translate("PTZ-Preset") . " 19", "", 0x00ff00);
                        IPS_SetVariableProfileAssociation("BlueIris.Preset", 120, $this->Translate("PTZ-Preset") . " 20", "", 0x00ff00);
                    }
                    if (!IPS_VariableProfileExists("BlueIris.Move")) {
                        IPS_CreateVariableProfile("BlueIris.Move", 1);
                        IPS_SetVariableProfileValues("BlueIris.Move", -1, 1, 1);
                    }

                    $this->RegisterVariableInteger("PTZPreset", $this->Translate("PTZ-Preset"), "BlueIris.Preset", 10);
                    $this->RegisterVariableInteger("PTZMoveLeftRight", $this->Translate("PTZ-Move Left/Right"), "BlueIris.Move", 11);
                    $this->RegisterVariableInteger("PTZMoveUPDown", $this->Translate("PTZ-Move UP/Down"), "BlueIris.Move", 12);
                    $this->RegisterVariableInteger("PTZMoveZoom", $this->Translate("PTZ-Move Zoom"), "BlueIris.Move", 13);

                    $this->EnableAction("PTZPreset");
                    $this->EnableAction("PTZMoveLeftRight");
                    $this->EnableAction("PTZMoveUPDown");
                    $this->EnableAction("PTZMoveZoom");

                    if ($this->GetValue("PTZPreset") < 101 || $this->GetValue("PTZPreset") > 120) $this->SetValue("PTZPreset", 101);
                }
            }

            if($this->ReadPropertyBoolean("showFPS")){
                $this->RegisterVariableInteger("FPS",  $this->Translate("FPS"), "", 99);
            }

        }

        public function RequestAction($Ident, $Value) {
            switch($Ident) {
                case "PTZPreset":
                    if($Value >= 101 && $Value <= 120){
                        $this->PTZ($Value,0);
                    }
                    $this->SetValue($Ident, $Value);
                    break;
                case "PTZMoveLeftRight":
                    if($Value < 0){
                        $this->PTZ(0,0);
                    }elseif($Value > 0){
                        $this->PTZ(1,0);
                    }
                    $this->SetValue($Ident, 0);
                    break;
                case "PTZMoveUPDown":
                    if($Value > 0){
                        $this->PTZ(2,0);
                    }elseif($Value < 0){
                        $this->PTZ(3,0);
                    }
                    $this->SetValue($Ident, 0);
                    break;
                case "PTZMoveZoom":
                    if($Value > 0){
                        $this->PTZ(5,0);
                    }elseif($Value < 0){
                        $this->PTZ(6,0);
                    }
                    $this->SetValue($Ident, 0);
                    break;
                case "isEnabled":
                case "isPaused":
                    $this->SetValue($Ident, $Value);
                    $this->UpdateCamConfig();
                    break;
                case "isTriggered":
                    $this->SetValue($Ident, $Value);
                    if($Value){
                        $this->Trigger();
                        if ($this->ReadPropertyBoolean("Debug")) {
                            $this->SendDebug(__FUNCTION__, "Aufnahme angefordert", 0);
                        }
                    }
                    break;
                default:
                    throw new Exception("Invalid Ident");
            }
        }
        public function ReceiveData($JSONString)
        {
            $message = DataMessage::decode($JSONString, DataMessage::CAMERA_UPDATE);
            $shortName = $this->ReadPropertyString("ShortName");
            if (
                $shortName === ""
                || ($message['cmd'] ?? null) !== 'CamList'
                || ($message['sname'] ?? null) !== $shortName
                || !is_array($message['payload'] ?? null)
            ) {
                return;
            }

            $this->UpdateConfig($message['payload']);
        }

        private function IsIndexCamera(): bool
        {
            return in_array($this->ReadPropertyString("ShortName"), ["Index", "@Index"], true);
        }

        private function UpdateConfig(array $config): void
        {
            $idents = ['isMotion', 'isTriggered'];
            if (!$this->IsIndexCamera()) {
                $idents = array_merge($idents, ['isEnabled', 'isAlerting', 'isRecording', 'isOnline', 'isPaused', 'isNoSignal']);
            }

            foreach ($idents as $ident) {
                $value = $config[$ident] ?? null;
                if (is_bool($value) || in_array($value, [0, 1, '0', '1'], true)) {
                    $this->SetValue($ident, (bool) $value);
                }
            }

            if ($this->ReadPropertyBoolean("showFPS") && is_numeric($config['FPS'] ?? null)) {
                $this->SetValue("FPS", (int) $config['FPS']);
            }
        }

        private function UpdateCamConfig(){
            $enable = $this->GetValue("isEnabled");
            if($this->GetValue("isPaused")){
                $pause = -1; //-1 pause| 0 stop pause
            }else{
                $pause = 0; //-1 pause| 0 stop pause
            }

            $result = $this->CamConfig(null, $enable, $pause);
            if ($this->ReadPropertyBoolean("Debug")) {
                $this->SendDebug(__FUNCTION__, "Kamerakonfiguration angefordert", 0);
            }

            return $result;
        }

        public function CamConfig(?bool $reset = null, ?bool $enable = null, ?int $pause = null, ?bool $motion = null, ?bool $schedule = null, ?bool $ptzcycle = null, ?bool $ptzevents = null, ?int $alerts = null, ?int $record = null){
            $camera = $this->ReadPropertyString("ShortName");

            $data = array();
            $data["camera"] = $camera;
            if(!is_null($reset)) $data["reset"] = $reset;
            if(!is_null($enable)) $data["enable"] = $enable;
            if(!is_null($pause)) $data["pause"] = $pause;
            if(!is_null($motion)) $data["motion"] = $motion;
            if(!is_null($schedule)) $data["schedule"] = $schedule;
            if(!is_null($ptzcycle)) $data["ptzcycle"] = $ptzcycle;
            if(!is_null($ptzevents)) $data["ptzevents"] = $ptzevents;
            if(!is_null($alerts)) $data["alerts"] = $alerts;
            if(!is_null($record)) $data["record"] = $record;

            $sendData = array("cmd" => "CamConfig", "cam" => $camera, "data" => $data);
            return $this->SendDataToParent(DataMessage::encode(DataMessage::CAMERA_REQUEST, $sendData));
        }

        public function AlertList(?int $startdate = null, ?bool $reset = null){
            $data = array();
            $data["camera"] = $this->ReadPropertyString("ShortName");
            if(is_null($startdate)) $startdate = 0;
            if(is_null($reset)) $reset = false;

            $data["startdate"] = $startdate;
            $data["reset"] = $reset;

            $sendData = array("cmd" => "AlertList", "data" => $data);
            return $this->SendDataToParent(DataMessage::encode(DataMessage::CAMERA_REQUEST, $sendData));
        }
        public function ClipList(?int $startdate = null, ?int $enddate = null, ?bool $tiles = null){
            $data = array();
            $data["camera"] = $this->ReadPropertyString("ShortName");
            if(is_null($startdate)) $startdate = 0;
            if(is_null($enddate)) $enddate = time();
            if(is_null($tiles)) $tiles = false;

            $data["startdate"] = $startdate;
            $data["enddate"] = $enddate;
            $data["tiles"] = $tiles;

            $sendData = array("cmd" => "ClipList","data" => $data);
            return $this->SendDataToParent(DataMessage::encode(DataMessage::CAMERA_REQUEST, $sendData));
        }
        public function PTZ(int $button = 4, ?int $updown = null){
            $data = array();
            $data["camera"] = $this->ReadPropertyString("ShortName");
            $data["button"] = $button;
            $data["updown"] = $updown ?? 0;

            $sendData = array("cmd" => "PTZ", "data" => $data);
            return $this->SendDataToParent(DataMessage::encode(DataMessage::CAMERA_REQUEST, $sendData));
        }
        public function Trigger(){
            $data = array();
            $data["camera"] = $this->ReadPropertyString("ShortName");

            $sendData = array("cmd" => "Trigger", "data" => $data);
            return $this->SendDataToParent(DataMessage::encode(DataMessage::CAMERA_REQUEST, $sendData));
        }

        public function CreateMediaFile(){
            if (@IPS_GetObjectIDByIdent("Stream", $this->InstanceID) !== false) {
                return;
            }

            $url = $this->GetStreamURL();
            if ($url === null || !$this->CanOpenStream($url)) {
                return;
            }

            $mediaID = IPS_CreateMedia(3);
            if (!IPS_SetMediaFile($mediaID, $url, true)) {
                IPS_DeleteMedia($mediaID, false);
                return;
            }
            IPS_SetName($mediaID, "Stream");
            IPS_SetParent($mediaID, $this->InstanceID);
            IPS_SetIdent($mediaID, "Stream");
        }

        public function UpdateMediaFile(): bool
        {
            $mediaID = @IPS_GetObjectIDByIdent("Stream", $this->InstanceID);
            if ($mediaID === false || !IPS_MediaExists($mediaID) || IPS_GetMedia($mediaID)['MediaType'] !== 3) {
                return false;
            }

            $url = $this->GetStreamURL();
            if ($url === null || !$this->CanOpenStream($url)) {
                return false;
            }

            return IPS_SetMediaFile($mediaID, $url, true);
        }

        private function GetStreamURL(): ?string
        {
            $shortName = $this->ReadPropertyString("ShortName");
            if ($shortName === '') {
                return null;
            }

            $response = $this->SendDataToParent(DataMessage::encode(DataMessage::CAMERA_REQUEST, ['cmd' => 'GetLink']));
            $stream = is_string($response) ? json_decode($response, true) : null;
            if (!is_string($stream['link'] ?? null) || $stream['link'] === '') {
                return null;
            }

            $url = $stream['link'] . '/mjpg/' . rawurlencode($shortName) . '/video.mjpg';
            $user = $stream['user'] ?? '';
            $password = $stream['pw'] ?? '';
            if (is_string($user) && $user !== '' && is_string($password) && $password !== '') {
                $url .= '?' . http_build_query(['user' => $user, 'pw' => $password], '', '&', PHP_QUERY_RFC3986);
            }
            return $url;
        }

        private function CanOpenStream(string $url): bool
        {
            if (strpos($url, 'https://') !== 0) {
                return true;
            }
            if (!function_exists('curl_init')) {
                return false;
            }

            $received = false;
            $handle = curl_init($url);
            if ($handle === false) {
                return false;
            }
            curl_setopt_array($handle, [
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION  => static function ($handle, string $chunk) use (&$received): int {
                    $received = $chunk !== '';
                    return 0;
                }
            ]);
            curl_exec($handle);

            $type = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
            return $received
                && curl_errno($handle) === CURLE_WRITE_ERROR
                && curl_getinfo($handle, CURLINFO_HTTP_CODE) === 200
                && stripos($type, 'multipart/x-mixed-replace') === 0;
        }
    }
