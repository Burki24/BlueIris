<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_SERVER['REQUEST_URI'] !== '/json') {
    http_response_code(404);
    return;
}

header('Content-Type: application/json');
$request = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
$session = $request['session'] ?? '';
$command = $request['cmd'] ?? '';
$success = ['result' => 'success'];
$failure = ['result' => 'fail'];
$fixtureSession = 'fixture-session';

if ($command === 'login') {
    if ($session === '') {
        $response = $failure + ['session' => $fixtureSession];
    } elseif ($session === $fixtureSession && ($request['response'] ?? '') === md5('fixture-user:fixture-session:fixture-password')) {
        $response = $success + ['session' => $fixtureSession];
    } else {
        $response = $failure;
    }
} elseif ($session !== $fixtureSession || ($_COOKIE['PHPSESSID'] ?? '') !== $fixtureSession) {
    $response = $failure;
} else {
    switch ($command) {
        case 'camlist':
            $response = $success + ['data' => [
                ['optionValue' => 'Front.1', 'optionDisplay' => 'Eingang Süd', 'isRecording' => true],
                ['optionValue' => 'Front.10', 'optionDisplay' => 'Eingang Nord', 'isRecording' => false],
                ['optionValue' => 'Other', 'optionDisplay' => 'Garage']
            ]];
            break;
        case 'status':
            $response = $success + ['data' => [
                'profile'  => $request['profile'] ?? 1,
                'cpu'      => 36,
                'ram'      => 64 * 1024 * 1024,
                'uptime'   => '00:17:32',
                'schedule' => 'Test'
            ]];
            break;
        case 'cliplist':
        case 'alertlist':
        case 'log':
            $response = $success + ['data' => [$command]];
            break;
        case 'sysconfig':
        case 'camconfig':
            unset($request['cmd'], $request['session']);
            $response = $success + ['data' => $request];
            break;
        case 'ptz':
            $response = ($request['updown'] ?? null) === 1 ? $success : $failure;
            break;
        case 'trigger':
        case 'logout':
            $response = $success;
            break;
        case 'http-error':
            http_response_code(503);
            $response = $failure;
            break;
        case 'invalid-json':
            echo '<html>';
            return;
        case 'timeout':
            usleep(1_500_000);
            $response = $success;
            break;
        default:
            $response = $failure;
            break;
    }
}

echo json_encode($response, JSON_THROW_ON_ERROR);
