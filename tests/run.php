<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$stubRoot = getenv('SYMCON_STUBS_ROOT')
    ?: (is_file(__DIR__ . '/stubs/autoload.php') ? __DIR__ . '/stubs' : dirname($root) . '/Symcon_ModuleCI/tests/stubs');
if (!is_file($stubRoot . '/autoload.php')) {
    throw new RuntimeException('Set SYMCON_STUBS_ROOT to a checkout of symcon/SymconStubs.');
}

require_once $stubRoot . '/autoload.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool
{
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
error_reporting(E_ALL);

function check($expected, $actual, string $context): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($context . ': expected ' . var_export($expected, true) . ', received ' . var_export($actual, true));
    }
}

function value(int $instance, string $ident)
{
    return GetValue(IPS_GetObjectIDByIdent($ident, $instance));
}

function formValues(int $configurator): array
{
    $form = IPS\InstanceManager::getInstanceInterface($configurator)->GetConfigurationForm();
    return json_decode($form, true, 512, JSON_THROW_ON_ERROR)['elements'][0]['values'];
}

function mediaID(int $camera)
{
    return IPS_GetObjectIDByIdent('Stream', $camera);
}

IPS\Kernel::reset();
IPS_CreateVariableProfile('~Switch', 0);
IPS_CreateVariableProfile('~Intensity.100', 1);
IPS\ModuleLoader::loadLibrary($root . '/library.json');

class TimedBlueIrisGateway extends BlueIrisGateway
{
    protected function getTime()
    {
        return 0;
    }
}

class TimedBlueIrisSystem extends SymconBlueIrisSystem
{
    protected function getTime()
    {
        return 0;
    }
}

// The official stubs require modules under test to supply a clock.
$moduleRegistry = new ReflectionProperty(IPS\ModuleLoader::class, 'modules');
$modules = $moduleRegistry->getValue();
$modules['{E138AFDC-D1E0-B462-A5E5-AF24F57D4686}']['Class'] = TimedBlueIrisGateway::class;
$modules['{CDFC7E83-C425-E923-9B6F-ECB22F3DFCC9}']['Class'] = TimedBlueIrisSystem::class;
$moduleRegistry->setValue(null, $modules);

$fixtureRouter = __DIR__ . '/blue-iris-fixture.php';
$socket = stream_socket_server('tcp://127.0.0.1:0');
if ($socket === false) {
    throw new RuntimeException('Could not reserve a local fixture port.');
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$port = (int) substr(strrchr($address, ':'), 1);
$discard = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
$fixture = proc_open(
    [PHP_BINARY, '-S', $address, $fixtureRouter],
    [0 => ['file', $discard, 'r'], 1 => ['file', $discard, 'a'], 2 => ['file', $discard, 'a']],
    $pipes,
    __DIR__
);
if (!is_resource($fixture)) {
    throw new RuntimeException('Could not start the Blue Iris fixture.');
}

try {
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);
        if ($connection !== false) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(20_000);
    }
    check(true, $ready, 'Loopback fixture is listening');

    $gatewayModule = '{E138AFDC-D1E0-B462-A5E5-AF24F57D4686}';
    $cameraModule = '{5308D185-A3D2-42D0-B6CE-E9D3080CE184}';
    $systemModule = '{CDFC7E83-C425-E923-9B6F-ECB22F3DFCC9}';
    $configuratorModule = '{3B880DC9-B669-B9A1-A08B-AFE7CF6D9BD9}';
    $gateway = IPS_CreateInstance($gatewayModule);
    $otherGateway = IPS_CreateInstance($gatewayModule);
    foreach (['IPAddress' => '127.0.0.1', 'Port' => $port, 'Timeout' => 1, 'Username' => 'fixture-user', 'Password' => 'fixture-password', 'Debug' => true] as $name => $setting) {
        IPS_SetProperty($gateway, $name, $setting);
    }
    IPS_ApplyChanges($gateway);

    $camera = IPS_CreateInstance($cameraModule);
    IPS_SetProperty($camera, 'ShortName', 'Front.1');
    IPS_ApplyChanges($camera);
    $otherCamera = IPS_CreateInstance($cameraModule);
    IPS_SetProperty($otherCamera, 'ShortName', 'Front.1');
    IPS_ApplyChanges($otherCamera);
    IPS_DisconnectInstance($otherCamera);
    IPS_ConnectInstance($otherCamera, $otherGateway);
    $system = IPS_CreateInstance($systemModule);
    $otherSystem = IPS_CreateInstance($systemModule);
    IPS_DisconnectInstance($otherSystem);
    IPS_ConnectInstance($otherSystem, $otherGateway);
    $configurator = IPS_CreateInstance($configuratorModule);
    $otherConfigurator = IPS_CreateInstance($configuratorModule);
    IPS_DisconnectInstance($otherConfigurator);
    IPS_ConnectInstance($otherConfigurator, $otherGateway);

    $gatewayInterface = IPS\InstanceManager::getInstanceInterface($gateway);
    $systemInterface = IPS\InstanceManager::getInstanceInterface($system);
    $timer = new ReflectionMethod(IPSModule::class, 'GetTimerInterval');
    check(10000, $timer->invoke($gatewayInterface, 'SyncData'), 'Gateway timer uses milliseconds');
    check(0, $timer->invoke($systemInterface, 'SyncData'), 'System timer is disabled by default');
    IPS_SetProperty($system, 'GetClips', true);
    IPS_SetProperty($system, 'GetAlerts', true);
    IPS_SetProperty($system, 'GetLog', true);
    IPS_ApplyChanges($system);
    check(60000, $timer->invoke($systemInterface, 'SyncData'), 'Enabled system timer uses milliseconds');

    $recording = IPS_GetObjectIDByIdent('isRecording', $camera);
    $cpu = IPS_GetObjectIDByIdent('CPU', $system);
    BlueIrisGateway_SyncData($gateway);
    check(true, value($camera, 'isRecording'), 'Matching camera is updated and the camera with a longer name is ignored');
    check(false, value($otherCamera, 'isRecording'), 'Camera on the other gateway is untouched');
    check(36, value($system, 'CPU'), 'System status arrives through the gateway');
    check(64.0, value($system, 'Mem'), 'RAM bytes are converted to MiB');
    check(0, value($otherSystem, 'CPU'), 'Other gateway system is untouched');

    $rows = formValues($configurator);
    check($system, $rows[0]['instanceID'], 'Configurator uses this gateway system');
    $cameras = array_column(array_slice($rows, 2), null, 'shortName');
    check($camera, $cameras['Front.1']['instanceID'], 'Configurator uses this gateway camera');
    check('Eingang Süd', $cameras['Front.1']['name'], 'UTF-8 survives the HTTP and Symcon data flow');
    check(0, $cameras['Front.10']['instanceID'], 'Unconfigured camera appears as a create option');
    $otherRows = formValues($otherConfigurator);
    check($otherSystem, $otherRows[0]['instanceID'], 'Second configurator uses its own system');
    check($otherCamera, $otherRows[2]['instanceID'], 'Second configurator lists its camera even before discovery');

    $cameraInterface = IPS\InstanceManager::getInstanceInterface($camera);
    $cameraInterface->ReceiveData('malformed-json');
    $cameraInterface->ReceiveData(json_encode(['DataID' => '{00000000-0000-0000-0000-000000000000}', 'Buffer' => '{}']));
    $systemInterface->ReceiveData('{"Buffer":"{}"}');
    check(null, $gatewayInterface->ForwardData('{"Buffer":[] }'), 'Malformed command does not reach Blue Iris');
    check(true, value($camera, 'isRecording'), 'Malformed messages preserve camera status');

    check(['camera' => 'Front.1', 'enable' => false], json_decode($cameraInterface->CamConfig(null, false), true), 'Camera config keeps optional false values');
    check('success', SymconBlueIrisCam_PTZ($camera, 1, 1), 'PTZ passes the up/down option');
    check('success', SymconBlueIrisCam_Trigger($camera), 'Recording trigger response is preserved');
    check(3, json_decode($systemInterface->Status(null, 3), true)['profile'], 'System status command forwards the requested profile');
    check(false, json_decode($systemInterface->SysConfig(false), true)['archive'], 'System config forwards optional false values');
    SymconBlueIrisSystem_SyncData($system);
    foreach (['Clips' => 'cliplist', 'Alerts' => 'alertlist', 'Log' => 'log'] as $ident => $response) {
        check([$response], json_decode(value($system, $ident), true), 'Optional system data arrives for ' . $ident);
    }

    SymconBlueIrisCam_CreateMediaFile($camera);
    $media = mediaID($camera);
    check(true, IPS_MediaExists($media), 'Stream media is created');
    SymconBlueIrisCam_CreateMediaFile($camera);
    check($media, mediaID($camera), 'Creating the stream again preserves the media ID');

    $cameraLinkRequest = json_encode([
        'DataID' => '{95E9AD15-F5B0-01C6-4FF2-9093B9BD4E36}',
        'Buffer' => '{"cmd":"GetLink"}'
    ], JSON_THROW_ON_ERROR);
    check('http://127.0.0.1:' . $port, json_decode($gatewayInterface->ForwardData($cameraLinkRequest), true)['link'], 'Existing gateways default to HTTP');

    $otherGatewayInterface = IPS\InstanceManager::getInstanceInterface($otherGateway);
    foreach (['IPAddress' => '127.0.0.1', 'Port' => $port, 'Timeout' => 1, 'Protocol' => 'https'] as $name => $setting) {
        IPS_SetProperty($otherGateway, $name, $setting);
    }
    IPS_ApplyChanges($otherGateway);
    check('https://127.0.0.1:' . $port, json_decode($otherGatewayInterface->ForwardData($cameraLinkRequest), true)['link'], 'MJPEG uses the configured HTTPS scheme and port');
    check('ERROR', BlueIrisGateway_Login($otherGateway), 'HTTPS rejects the HTTP fixture without falling back');
    check(false, IPS_GetInstance($otherGateway)['InstanceStatus'] === 102, 'The HTTPS connection failure is visible');

    IPS_SetProperty($otherGateway, 'TlsCertificate', "-----BEGIN CERTIFICATE-----\ninvalid\n-----END CERTIFICATE-----");
    IPS_ApplyChanges($otherGateway);
    check(206, IPS_GetInstance($otherGateway)['InstanceStatus'], 'Malformed PEM is rejected during HTTPS configuration');
    check(0, $timer->invoke($otherGatewayInterface, 'SyncData'), 'Malformed PEM disables automatic requests');
    check('ERROR', BlueIrisGateway_Login($otherGateway), 'The API does not fall back when a PEM is invalid');
    $link = json_decode($otherGatewayInterface->ForwardData($cameraLinkRequest), true);
    check(null, $link['link'], 'Malformed PEM blocks stream setup instead of using default trust');
    check(false, array_key_exists('tlsCertificate', $link), 'Malformed PEM is not forwarded to the camera');

    IPS_SetProperty($otherGateway, 'Protocol', 'http');
    IPS_ApplyChanges($otherGateway);
    check('http://127.0.0.1:' . $port, json_decode($otherGatewayInterface->ForwardData($cameraLinkRequest), true)['link'], 'The PEM field does not affect HTTP');

    IPS_SetProperty($otherGateway, 'Protocol', 'unsupported');
    IPS_ApplyChanges($otherGateway);
    check(201, IPS_GetInstance($otherGateway)['InstanceStatus'], 'Invalid protocols are rejected');
    check(null, json_decode($otherGatewayInterface->ForwardData($cameraLinkRequest), true)['link'], 'Invalid protocols yield no stream URL');

    $setBuffer = new ReflectionMethod(IPSModule::class, 'SetBuffer');
    $setBuffer->invoke($gatewayInterface, 'BlueIrisSession', 'expired-fixture-session');
    check('fixture-session', BlueIrisGateway_Login($gateway), 'Expired session is replaced');
    check(true, BlueIrisGateway_Logout($gateway, 'fixture-session'), 'Logout keeps the public boolean result');

    IPS_SetProperty($gateway, 'Password', 'fixture-incorrect');
    IPS_ApplyChanges($gateway);
    check('ERROR', BlueIrisGateway_Login($gateway), 'Wrong credentials return the legacy error value');
    check(205, IPS_GetInstance($gateway)['InstanceStatus'], 'Wrong credentials set the instance status');
    IPS_SetProperty($gateway, 'Password', 'fixture-password');
    IPS_ApplyChanges($gateway);

    check('{}', BlueIrisGateway_SendJSONData($gateway, 'http-error', '{}'), 'HTTP error is contained');
    check(203, IPS_GetInstance($gateway)['InstanceStatus'], 'HTTP errors set the instance status');
    check('{}', BlueIrisGateway_SendJSONData($gateway, 'invalid-json', '{}'), 'Unexpected response body is contained');
    check(203, IPS_GetInstance($gateway)['InstanceStatus'], 'Malformed JSON sets the instance status');

    IPS_ApplyChanges($camera);
    IPS_ApplyChanges($system);
    check($recording, IPS_GetObjectIDByIdent('isRecording', $camera), 'Reapplying does not replace camera variables');
    check($cpu, IPS_GetObjectIDByIdent('CPU', $system), 'Reapplying does not replace system variables');
    check($media, mediaID($camera), 'Reapplying preserves the stream media object');

    check('{}', BlueIrisGateway_SendJSONData($gateway, 'timeout', '{}'), 'A connected server that stalls times out');
    check(204, IPS_GetInstance($gateway)['InstanceStatus'], 'Timeout sets the instance status');
    $debug = json_encode(IPS\DebugServer::getDebugMessages($gateway), JSON_THROW_ON_ERROR);
    check(true, str_contains($debug, 'Blue Iris'), 'Gateway records safe diagnostic context');
    foreach (['fixture-password', 'fixture-incorrect', 'fixture-session', 'fixture-user'] as $secret) {
        check(false, str_contains($debug, $secret), 'Gateway diagnostics omit credentials and sessions');
    }

    $certificate = <<<'PEM'
-----BEGIN CERTIFICATE-----
MIIBlTCCATugAwIBAgIUV7N6rT1vcxEdHac5VsRBE4OuWkkwCgYIKoZIzj0EAwIw
IDEeMBwGA1UEAwwVYmx1ZWlyaXMtdGVzdC5pbnZhbGlkMB4XDTI2MTEyNjE1MjM1
OVoXDTI3MTEyNjE1MjM1OVowIDEeMBwGA1UEAwwVYmx1ZWlyaXMtdGVzdC5pbnZh
bGlkMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEPw1ii6eoPU6yP/SeLlTzF58D
SDY6z4hp7hsbwO+Q5A8WyQxwwwnV3gKbFb02KtEsXWEa2AsRoafjlmFslvVbL6NT
MFEwHQYDVR0OBBYEFOSJ1OvhZEeuffgEAHGsanGxjlCYMB8GA1UdIwQYMBaAFOSJ
1OvhZEeuffgEAHGsanGxjlCYMA8GA1UdEwEB/wQFMAMBAf8wCgYIKoZIzj0EAwID
SAAwRQIgZVMHAJw1jrzp3Ky3Ax8PPPtKKTH20PcrX3xaB9yyp9gCIQCmLWykdbsv
qjcZLMmuZA6w2nheXL0WAypCegtcvuNB6w==
-----END CERTIFICATE-----
PEM;
    $form = json_decode($gatewayInterface->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
    $labels = array_column($form['elements'], 'caption', 'name');
    check('HTTPS uses the Symcon trust store', $labels['TlsCertificateStatus'], 'Without a custom certificate the form shows default trust');
    $panels = array_values(array_filter($form['elements'], static fn (array $field): bool => $field['type'] === 'ExpansionPanel'));
    check(false, $panels[0]['expanded'], 'Manual PEM input starts collapsed');
    check('TlsCertificate', $panels[0]['items'][0]['name'], 'Manual editing keeps the existing property');
    check('SelectFile', $form['actions'][1]['popup']['items'][1]['type'], 'The import dialog offers a file selector');

    $upload = base64_encode("\xEF\xBB\xBF" . $certificate . "\r\n");
    IPS_SetProperty($gateway, 'Interval', 11);
    check('Apply or discard pending gateway changes before importing.', BlueIrisGateway_ImportTlsCertificate($gateway, $upload), 'Import preserves unrelated pending edits');
    check('', json_decode(IPS_GetConfiguration($gateway), true)['TlsCertificate'], 'Pending changes block certificate replacement');
    IPS_ResetChanges($gateway);

    check('', BlueIrisGateway_ImportTlsCertificate($gateway, $upload), 'Import accepts a public PEM with a UTF-8 BOM');
    $saved = IPS_GetConfiguration($gateway);
    check($certificate, json_decode($saved, true)['TlsCertificate'], 'Import saves only the decoded certificate');
    $form = json_decode($gatewayInterface->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
    $labels = array_column($form['elements'], 'caption', 'name');
    check('PEM certificate stored for HTTPS', $labels['TlsCertificateStatus'], 'The saved certificate has a compact status');

    $invalid = 'The file must contain only public PEM certificates supported by this PHP runtime.';
    check($invalid, BlueIrisGateway_ImportTlsCertificate($gateway, '!!'), 'Invalid base64 is rejected');
    check($invalid, BlueIrisGateway_ImportTlsCertificate($gateway, base64_encode($certificate . "\n-----BEGIN PRIVATE KEY-----\nTEST\n-----END PRIVATE KEY-----")), 'Files with a private key section are rejected');
    check('The certificate file exceeds 64 KiB.', BlueIrisGateway_ImportTlsCertificate($gateway, base64_encode(str_repeat('X', 64 * 1024 + 1))), 'Oversized files are rejected');
    check($saved, IPS_GetConfiguration($gateway), 'Rejected uploads preserve the saved certificate and surrounding settings');

    echo "BlueIris integration checks passed.\n";
} finally {
    proc_terminate($fixture);
    proc_close($fixture);
}
