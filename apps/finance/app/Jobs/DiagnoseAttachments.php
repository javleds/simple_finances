<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TransactionAttachments\ConvertAttachmentImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DiagnoseAttachments implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public readonly string $token)
    {
    }

    public function handle(ConvertAttachmentImage $convert): void
    {
        $disk = Storage::disk(config('transaction_attachments.disk'));
        $base = 'diagnostics/'.$this->token;
        try {
            $disk->put($base.'.heic', base64_decode('AAAAHGZ0eXBoZWl4AAAAAG1pZjFoZWl4bWlhZgAAAWhtZXRhAAAAAAAAACFoZGxyAAAAAAAAAABwaWN0AAAAAAAAAAAAAAAAAAAAACJpbG9jAAAAAERAAAEAAQAAAAABjAABAAAAAAAAABYAAAAjaWluZgAAAAAAAQAAABVpbmZlAgAAAAABAABodmMxAAAAAA5waXRtAAAAAAABAAAA6GlwcnAAAADJaXBjbwAAAHVodmNDAQQIAAAAAAAAAAAAHvAA/P38/AAADwNgAAEAF0ABDAH//wQIAAADAJm4AAADAAAeugJAYQABACpCAQEECAAAAwCZuAAAAwAAHqAggQRSlurkprm4CGgwIAAAAwMgAAADACFiAAEABkQBwXPAiQAAABRpc3BlAAAAAAAAAEAAAABAAAAAKGNsYXAAAAAgAAAAAQAAACAAAAAB////4AAAAAL////gAAAAAgAAABBwaXhpAAAAAAMMDAwAAAAXaXBtYQAAAAAAAAABAAEEgQIEgwAAAB5tZGF0AAAAEigBrxOA9sGng6h/hVYKI6qAcA=='));
            $convert->execute($disk->path($base.'.heic'), $disk->path($base.'.webp'));
            if (! extension_loaded('imagick') || \Imagick::queryFormats('HEIC') === []) {
                throw new \RuntimeException('HEIC no está disponible.');
            }
            $disk->put($base.'.result', 'ok');
        } catch (Throwable $exception) {
            $disk->put($base.'.result', 'error: '.$exception->getMessage());
        } finally {
            $disk->delete([$base.'.heic', $base.'.webp']);
        }
    }
}
