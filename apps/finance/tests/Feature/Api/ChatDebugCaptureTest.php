<?php

declare(strict_types=1);

use App\Services\Chat\PurgeConversationDebugCaptures;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

it('removes only local debug captures referencing the deleted thread', function (): void {
    $path = sys_get_temp_dir() . '/chat-captures-' . Str::uuid();
    File::makeDirectory($path);
    config(['debugbar.storage.path' => $path, 'debugbar.storage.driver' => 'file']);
    $thread = (string) Str::uuid();
    File::put($path . '/thread.json', json_encode(['uri' => '/api/chat/threads/' . $thread . '/messages']));
    File::put($path . '/other.json', json_encode(['uri' => '/api/accounts']));
    File::put($path . '/keep.txt', $thread);

    try {
        app(PurgeConversationDebugCaptures::class)->execute($thread);
        expect(File::exists($path . '/thread.json'))->toBeFalse();
        expect(File::exists($path . '/other.json'))->toBeTrue();
        expect(File::exists($path . '/keep.txt'))->toBeTrue();
        app(PurgeConversationDebugCaptures::class)->execute($thread);
    } finally {
        File::deleteDirectory($path);
    }
});
