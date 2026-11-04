<?php

declare(strict_types=1);

namespace App\Services\TransactionAttachments;

use Imagick;

class ConvertAttachmentImage
{
    public function execute(string $source, string $destination): array
    {
        if (! extension_loaded('imagick')) {
            throw new AttachmentProcessingException('El procesamiento de imágenes no está disponible. Intenta más tarde.');
        }
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, (int) config('transaction_attachments.processing_memory_bytes'));
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, 0);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_DISK, (int) config('transaction_attachments.processing_disk_bytes'));
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, (int) config('transaction_attachments.processing_timeout'));
        $temporaryPath = \Illuminate\Support\Facades\Storage::disk(config('transaction_attachments.disk'))->path('workspace');
        if (! is_dir($temporaryPath) && ! mkdir($temporaryPath, 0700, true) && ! is_dir($temporaryPath)) {
            throw new AttachmentProcessingException('No se pudo preparar el procesamiento de imágenes.');
        }
        Imagick::setRegistry('temporary-path', $temporaryPath);
        $image = new Imagick();
        try {
            $image->pingImage($source.'[0]');
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            if ($width < 1 || $height < 1 || $width * $height > config('transaction_attachments.max_image_pixels')) {
                throw new AttachmentProcessingException('La imagen excede las dimensiones permitidas.');
            }
            $image->clear();
            $image->readImage($source.'[0]');
            $image->autoOrient();
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            $dimension = (int) config('transaction_attachments.image_max_dimension');
            if ($image->getImageWidth() > $dimension || $image->getImageHeight() > $dimension) {
                $image->thumbnailImage($dimension, $dimension, true);
            }
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality((int) config('transaction_attachments.webp_quality'));
            $image->writeImage($destination);
            if (filesize($destination) > config('transaction_attachments.max_file_bytes.image')) {
                throw new AttachmentProcessingException('La imagen procesada excede el tamaño permitido.');
            }
            return ['width' => $image->getImageWidth(), 'height' => $image->getImageHeight()];
        } finally {
            $image->clear();
        }
    }
}
