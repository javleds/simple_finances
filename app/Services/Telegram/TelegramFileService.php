<?php

namespace App\Services\Telegram;

use App\Contracts\TelegramServiceInterface;
use Illuminate\Support\Facades\Storage;

class TelegramFileService
{
    public function __construct(
        private readonly TelegramServiceInterface $telegramService
    ) {}

    public function getFileFromPhoto(array $photos): ?array
    {
        if (empty($photos)) {
            return null;
        }

        $largestPhoto = collect($photos)->sortByDesc('file_size')->first();

        return $this->getFileInfo($largestPhoto['file_id']);
    }

    public function getFileFromVideo(array $videoData): ?array
    {
        if (empty($videoData) || !isset($videoData['file_id'])) {
            return null;
        }

        return $this->getFileInfo($videoData['file_id']);
    }

    public function getFileFromVoice(array $voiceData): ?array
    {
        if (empty($voiceData) || !isset($voiceData['file_id'])) {
            return null;
        }

        return $this->getFileInfo($voiceData['file_id']);
    }

    public function getFileFromAudio(array $audioData): ?array
    {
        if (empty($audioData) || !isset($audioData['file_id'])) {
            return null;
        }

        return $this->getFileInfo($audioData['file_id']);
    }

    public function getFileFromDocument(array $documentData): ?array
    {
        if (empty($documentData) || !isset($documentData['file_id'])) {
            return null;
        }

        return $this->getFileInfo($documentData['file_id']);
    }

    private function getFileInfo(string $fileId): array
    {
        $fileInfo = $this->telegramService->getFile($fileId);

        return [
            'file_id' => $fileInfo['file_id'],
            'file_unique_id' => $fileInfo['file_unique_id'],
            'file_size' => $fileInfo['file_size'] ?? 0,
            'file_path' => $fileInfo['file_path'] ?? null,
            'download_url' => $fileInfo['file_path'] ? $this->telegramService->getFileUrl($fileInfo['file_path']) : null,
        ];
    }

    public function downloadAndStore(string $fileId, string $directory = 'telegram'): array
    {
        $fileInfo = $this->getFileInfo($fileId);

        if (!$fileInfo['file_path']) {
            throw new \Exception('No se pudo obtener la ruta del archivo');
        }

        $fileContent = $this->telegramService->downloadFile($fileInfo['file_path']);

        $fileName = $this->generateFileName($fileInfo);
        $storagePath = "{$directory}/{$fileName}";

        Storage::disk('local')->put($storagePath, $fileContent);

        return [
            'file_info' => $fileInfo,
            'storage_path' => $storagePath,
            'full_path' => Storage::disk('local')->path($storagePath),
        ];
    }

    public function downloadFileTemporarily(array $fileInfo): ?array
    {
        if (!isset($fileInfo['file_path'])) {
            return null;
        }

        try {
            $fileContent = $this->telegramService->downloadFile($fileInfo['file_path']);

            $fileName = $this->generateFileName($fileInfo);
            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'telegram_' . $fileName;

            file_put_contents($tempPath, $fileContent);

            return [
                'file_info' => $fileInfo,
                'storage_path' => $tempPath,
                'full_path' => $tempPath,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to download file temporarily', [
                'file_info' => $fileInfo,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    private function generateFileName(array $fileInfo): string
    {
        $extension = pathinfo($fileInfo['file_path'] ?? '', PATHINFO_EXTENSION);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $uniqueId = substr($fileInfo['file_unique_id'], 0, 8);

        return "{$timestamp}_{$uniqueId}" . ($extension ? ".{$extension}" : '');
    }

    public function getFileType(array $telegramUpdate): ?string
    {
        $message = data_get($telegramUpdate, 'message', []);

        if (!empty($message['photo'])) {
            return 'photo';
        }

        if (!empty($message['video'])) {
            return 'video';
        }

        if (!empty($message['voice'])) {
            return 'voice';
        }

        if (!empty($message['audio'])) {
            return 'audio';
        }

        if (!empty($message['document'])) {
            return 'document';
        }

        return null;
    }

    public function extractFileData(array $telegramUpdate): ?array
    {
        $fileType = $this->getFileType($telegramUpdate);

        if (!$fileType) {
            return null;
        }

        $message = data_get($telegramUpdate, 'message', []);

        return match($fileType) {
            'photo' => $this->getFileFromPhoto($message['photo']),
            'video' => $this->getFileFromVideo($message['video']),
            'voice' => $this->getFileFromVoice($message['voice']),
            'audio' => $this->getFileFromAudio($message['audio']),
            'document' => $this->getFileFromDocument($message['document']),
            default => null,
        };
    }

    public function autoDownloadFile(array $fileInfo, string $fileType = 'media'): ?array
    {
        if (!$fileInfo || !isset($fileInfo['file_id'])) {
            return null;
        }

        try {
            return $this->downloadAndStore($fileInfo['file_id'], "telegram/{$fileType}");
        } catch (\Exception $e) {
            return null;
        }
    }

    public function shouldAutoDownload(array $fileInfo, int $maxSizeBytes = 50971520): bool
    {
        return isset($fileInfo['file_size']) && $fileInfo['file_size'] <= $maxSizeBytes;
    }
}
