<?php

declare(strict_types=1);

namespace BlueIris;

final class DataMessage
{
    public const CAMERA_REQUEST = '{95E9AD15-F5B0-01C6-4FF2-9093B9BD4E36}';
    public const CONFIGURATOR_REQUEST = '{7024A8F5-E706-33B4-4BBE-19A510C35E65}';
    public const SYSTEM_REQUEST = '{8AB1599B-63C9-6E0E-864E-98A562E1CBC9}';
    public const CAMERA_UPDATE = '{99FD6BAA-68AA-D576-8209-4E8E7A33E3E7}';
    public const CONFIGURATOR_UPDATE = '{80FC55A5-8B0F-0642-DB3B-2CA825E3A2A3}';
    public const SYSTEM_UPDATE = '{8AD9F950-FFB3-1BE8-5B4C-1C603EB9887E}';

    public static function encode(string $dataID, array $message): string
    {
        return json_encode([
            'DataID' => $dataID,
            'Buffer' => json_encode($message, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)
        ], JSON_THROW_ON_ERROR);
    }

    public static function decode(string $json, string ...$dataIDs): ?array
    {
        $envelope = json_decode($json, true);
        if (
            !is_array($envelope)
            || !in_array($envelope['DataID'] ?? null, $dataIDs, true)
            || !is_string($envelope['Buffer'] ?? null)
        ) {
            return null;
        }

        $message = json_decode($envelope['Buffer'], true);
        return is_array($message) && is_string($message['cmd'] ?? null) ? $message : null;
    }
}
