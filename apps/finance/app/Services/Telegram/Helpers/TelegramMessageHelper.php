<?php

namespace App\Services\Telegram\Helpers;

use Illuminate\Support\Facades\Log;

class TelegramMessageHelper
{
    public static function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    public static function getUserName(array $telegramUpdate): string
    {
        return data_get($telegramUpdate, 'message.from.first_name', 'Usuario');
    }

    public static function isFileSizeAllowedForDownload(int $fileSize, int $maxSize = 20971520): bool
    {
        return $fileSize <= $maxSize;
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds >= 3600) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $remainingSeconds = $seconds % 60;

            return "{$hours}h {$minutes}m {$remainingSeconds}s";
        }

        if ($seconds >= 60) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;

            return "{$minutes}m {$remainingSeconds}s";
        }

        return "{$seconds}s";
    }

    public static function logFileProcessed(string $fileType, array $fileInfo, string $userName, ?array $downloadResult = null): void
    {
        $logData = [
            'file_type' => $fileType,
            'user_name' => $userName,
            'file_id' => $fileInfo['file_id'],
            'file_size' => $fileInfo['file_size'],
            'download_url' => $fileInfo['download_url'] ?? null,
        ];

        if ($downloadResult) {
            $logData['storage_path'] = $downloadResult['storage_path'];
            $logData['full_path'] = $downloadResult['full_path'];
        }

        Log::info("Archivo {$fileType} procesado", $logData);
    }

    public static function logFileError(string $fileType, \Exception $exception, string $userName, array $context = []): void
    {
        Log::error("Error procesando archivo {$fileType}", [
            'error' => $exception->getMessage(),
            'user_name' => $userName,
            'context' => $context,
        ]);
    }

    public static function hasPhoto(array $telegramUpdate): bool
    {
        return ! empty(data_get($telegramUpdate, 'message.photo'));
    }

    public static function hasCaption(array $telegramUpdate): bool
    {
        return ! empty(data_get($telegramUpdate, 'message.caption'));
    }

    public static function hasVideo(array $telegramUpdate): bool
    {
        return ! empty(data_get($telegramUpdate, 'message.video'));
    }

    public static function hasVoice(array $telegramUpdate): bool
    {
        return ! empty(data_get($telegramUpdate, 'message.voice'));
    }

    public static function hasText(array $telegramUpdate): bool
    {
        return ! empty(data_get($telegramUpdate, 'message.text'));
    }

    public static function hasAudio(array $telegramUpdate): bool
    {
        return ! empty(data_get($telegramUpdate, 'message.audio'));
    }

    public static function hasDocument(array $telegramUpdate): bool
    {
        return ! empty(data_get($telegramUpdate, 'message.document'));
    }

    public static function getCaption(array $telegramUpdate): string
    {
        return data_get($telegramUpdate, 'message.caption', '');
    }

    public static function getText(array $telegramUpdate): string
    {
        return data_get($telegramUpdate, 'message.text', '');
    }
}
