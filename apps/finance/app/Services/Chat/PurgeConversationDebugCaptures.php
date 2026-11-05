<?php

declare(strict_types=1);

namespace App\Services\Chat;

use function config;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

use function storage_path;
use function str_contains;

class PurgeConversationDebugCaptures
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    public function execute(string $threadId): void
    {
        $path = (string) config('debugbar.storage.path', storage_path('debugbar'));
        if ('file' !== config('debugbar.storage.driver', 'file') || ! $this->files->isDirectory($path)) {
            return;
        }

        foreach ($this->files->files($path) as $file) {
            if ($file->isLink() || 'json' !== $file->getExtension()) {
                continue;
            }
            if (! str_contains($file->getContents(), $threadId)) {
                continue;
            }
            if (! $this->files->delete($file->getPathname())) {
                throw new RuntimeException('Unable to remove a conversation debug capture.');
            }
        }
    }
}
