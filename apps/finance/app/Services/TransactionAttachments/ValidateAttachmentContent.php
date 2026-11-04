<?php

declare(strict_types=1);

namespace App\Services\TransactionAttachments;

use DOMDocument;
use ZipArchive;

class ValidateAttachmentContent
{
    public function execute(string $path, string $extension, bool $insideZip = false): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (in_array($extension, ['heic', 'heif'], true) && extension_loaded('imagick')) {
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, (int) config('transaction_attachments.processing_memory_bytes'));
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, 0);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_DISK, (int) config('transaction_attachments.processing_disk_bytes'));
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_THREAD, 1);
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_TIME, (int) config('transaction_attachments.processing_timeout'));
            $probe = new \Imagick();
            try {
                $probe->pingImage($path.'[0]');
                if ($probe->getImageWidth() * $probe->getImageHeight() > config('transaction_attachments.max_image_pixels')) {
                    $this->reject();
                }
            } finally {
                $probe->clear();
            }
        }
        if ($extension === 'xml') {
            $content = file_get_contents($path);
            if ($content === false || preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $content)) {
                $this->reject();
            }
            $previous = libxml_use_internal_errors(true);
            try {
                $document = new DOMDocument();
                $document->resolveExternals = false;
                $document->substituteEntities = false;
                if (! $document->loadXML($content, LIBXML_NONET) || $document->doctype !== null) {
                    $this->reject();
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            return 'application/xml';
        }
        if ($extension === 'pdf') {
            $stream = fopen($path, 'rb');
            try {
                $header = fread($stream, 8);
                fseek($stream, max(0, filesize($path) - 2048));
                $trailer = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
            if ($mime !== 'application/pdf' || ! preg_match('/^%PDF-[12]\.[0-9]/', $header) || ! preg_match('/%%EOF\s*$/', $trailer)) {
                $this->reject();
            }
            return 'application/pdf';
        }
        if ($extension === 'zip') {
            if ($insideZip || ! in_array($mime, ['application/zip', 'application/x-zip'], true)) {
                $this->reject();
            }
            $this->validateZip($path);
            return 'application/zip';
        }
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic', 'heif' => 'image/heif'];
        if (! isset($allowed[$extension]) || ! in_array($mime, [$allowed[$extension], 'image/heic-sequence', 'image/heif-sequence'], true)) {
            $this->reject();
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $dimensions = @getimagesize($path);
            if ($dimensions === false || $dimensions[0] * $dimensions[1] > config('transaction_attachments.max_image_pixels')) {
                $this->reject();
            }
        }
        return $mime;
    }

    private function validateZip(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            $this->reject();
        }
        $total = 0;
        try {
            if ($zip->numFiles > config('transaction_attachments.zip_max_entries')) {
                $this->reject();
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = $stat['name'];
                if (str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('/(^|\/)\.\.(\/|$)|^[a-z]:|[\x00-\x1f]/i', $name) || ($stat['encryption_method'] ?? 0) !== 0) {
                    $this->reject();
                }
                $zip->getExternalAttributesIndex($index, $system, $attributes);
                if (($attributes >> 16 & 0170000) === 0120000) {
                    $this->reject();
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (! in_array($extension, array_diff(config('transaction_attachments.accepted_extensions'), ['zip']), true)) {
                    $this->reject();
                }
                $limit = (int) config('transaction_attachments.max_file_bytes.'.(in_array($extension, ['pdf', 'xml'], true) ? $extension : 'image'));
                $entry = $zip->getStream($name);
                if ($entry === false) {
                    $this->reject();
                }
                $disk = \Illuminate\Support\Facades\Storage::disk(config('transaction_attachments.disk'));
                $disk->makeDirectory('workspace');
                $temporaryPath = $disk->path('workspace/zip-'.\Illuminate\Support\Str::uuid());
                $temporary = fopen($temporaryPath, 'x+b');
                if ($temporary === false) {
                    fclose($entry);
                    $this->reject();
                }
                chmod($temporaryPath, 0600);
                try {
                    $bytes = stream_copy_to_stream($entry, $temporary, min($limit, (int) config('transaction_attachments.zip_max_expanded_bytes') - $total) + 1);
                    $total += $bytes;
                    if ($bytes > $limit || $total > config('transaction_attachments.zip_max_expanded_bytes') || $bytes > max(1, $stat['comp_size']) * config('transaction_attachments.zip_max_ratio') || ! feof($entry)) {
                        $this->reject();
                    }
                    $this->execute($temporaryPath, $extension, true);
                } finally {
                    fclose($entry);
                    fclose($temporary);
                    unlink($temporaryPath);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function reject(): never
    {
        throw new AttachmentProcessingException('El archivo no es válido o contiene contenido no permitido.');
    }
}
