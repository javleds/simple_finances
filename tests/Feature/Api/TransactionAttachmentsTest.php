<?php

declare(strict_types=1);

use App\Jobs\ProcessTransactionAttachment;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\User;
use App\Services\TransactionAttachments\AcceptTransactionAttachment;
use App\Services\TransactionAttachments\ConvertAttachmentImage;
use App\Services\TransactionAttachments\MaintainAttachments;
use App\Services\TransactionAttachments\ProcessAttachment;
use App\Services\TransactionAttachments\ValidateAttachmentContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('transaction_attachments');
    Queue::fake();
    config(['transaction_attachments.min_free_disk_bytes' => 0]);
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->owner->id]);
    $this->account->users()->sync([$this->owner->id, $this->member->id]);
    $this->transaction = Transaction::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
    $this->url = '/api/transactions/'.$this->transaction->id.'/attachments';
    $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.\Tests\Support\AcceptedUserToken::generate($this->owner)['token']]);
});

function xmlAttachment(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('invoice.xml', '<?xml version="1.0"?><invoice total="10"/>');
}

it('accepts private asynchronous uploads idempotently without changing finances', function (): void {
    $uuid = (string) Str::uuid();
    $response = $this->post($this->url, ['file' => xmlAttachment(), 'upload_id' => $uuid])->assertStatus(202)->assertJsonPath('data.status', 'pending');
    $id = $response->json('data.id');
    $this->post($this->url, ['file' => xmlAttachment(), 'upload_id' => $uuid])->assertStatus(202)->assertJsonPath('data.id', $id);
    expect(TransactionAttachment::count())->toBe(1);
    Queue::assertPushed(ProcessTransactionAttachment::class, 1);
    $this->getJson($this->url)->assertOk()->assertJsonPath('meta.count', 1)->assertJsonMissingPath('data.0.source_path');
    $this->get($this->url.'/'.$id.'/download')->assertStatus(409);
    $attachment = TransactionAttachment::findOrFail($id);
    app(ProcessAttachment::class)->execute($id);
    expect($attachment->fresh()->path)->toBe('files/'.$id);
    $this->get($this->url.'/'.$id.'/download')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($attachment->fresh()->mime_type)->toBe('application/xml')->and($this->transaction->fresh()->amount)->toBe($this->transaction->amount);
});

it('allows current members to read but only authors to mutate', function (): void {
    $attachment = app(AcceptTransactionAttachment::class)->execute($this->transaction, $this->owner->id, xmlAttachment(), (string) Str::uuid());
    app(ProcessAttachment::class)->execute($attachment->id);
    $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.\Tests\Support\AcceptedUserToken::generate($this->member)['token']]);
    $this->getJson($this->url)->assertOk()->assertJsonPath('meta.can_manage', false);
    $this->get($this->url.'/'.$attachment->id.'/download')->assertOk();
    $this->deleteJson($this->url.'/'.$attachment->id)->assertForbidden();
    $this->post($this->url, ['file' => xmlAttachment(), 'upload_id' => (string) Str::uuid()])->assertForbidden();
    $this->account->users()->detach($this->member->id);
    $this->getJson($this->url)->assertForbidden();
    $this->get($this->url.'/'.$attachment->id.'/download')->assertForbidden();
});

it('enforces counts sizes global capacity and free disk', function (): void {
    config(['transaction_attachments.max_files' => 1]);
    $this->post($this->url, ['file' => xmlAttachment(), 'upload_id' => (string) Str::uuid()])->assertStatus(202);
    $this->post($this->url, ['file' => xmlAttachment(), 'upload_id' => (string) Str::uuid()])->assertStatus(422);
    config(['transaction_attachments.max_files' => 5, 'transaction_attachments.max_storage_bytes' => 1]);
    $this->post($this->url, ['file' => xmlAttachment(), 'upload_id' => (string) Str::uuid()])->assertStatus(507);
    config(['transaction_attachments.max_storage_bytes' => PHP_INT_MAX, 'transaction_attachments.min_free_disk_bytes' => PHP_INT_MAX]);
    $this->post($this->url, ['file' => xmlAttachment(), 'upload_id' => (string) Str::uuid()])->assertStatus(507);
    expect(TransactionAttachment::count())->toBe(1);
});

it('retains failed source for retry then expires it and cleans deleted movements', function (): void {
    $attachment = app(AcceptTransactionAttachment::class)->execute($this->transaction, $this->owner->id, UploadedFile::fake()->createWithContent('bad.xml', '<!DOCTYPE a><a/>'), (string) Str::uuid());
    expect(fn () => app(ProcessAttachment::class)->execute($attachment->id))->toThrow(RuntimeException::class);
    expect($attachment->fresh()->status)->toBe('failed');
    $this->postJson($this->url.'/'.$attachment->id.'/retry')->assertStatus(202);
    $attachment->update(['status' => 'failed', 'updated_at' => now()->subDays(2)]);
    app(MaintainAttachments::class)->execute();
    expect($attachment->fresh()->source_path)->toBeNull()->and(DB::table('transaction_attachment_capacity')->value('reserved_bytes'))->toBe(0);
    $this->postJson($this->url.'/'.$attachment->id.'/retry')->assertStatus(409);
    $this->transaction->delete();
    app(MaintainAttachments::class)->execute();
    expect(TransactionAttachment::count())->toBe(0);
});

it('revokes deleted files immediately and prevents jobs from reviving them', function (): void {
    $attachment = app(AcceptTransactionAttachment::class)->execute($this->transaction, $this->owner->id, xmlAttachment(), (string) Str::uuid());
    $this->deleteJson($this->url.'/'.$attachment->id)->assertOk();
    app(ProcessAttachment::class)->execute($attachment->id);
    $this->get($this->url.'/'.$attachment->id.'/download')->assertNotFound();
    app(MaintainAttachments::class)->execute();
    expect(Storage::disk('transaction_attachments')->allFiles('sources'))->toBe([]);
});

it('rejects XML external entities and unsafe ZIP entries', function (): void {
    $validator = app(ValidateAttachmentContent::class);
    foreach (['<!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><a>&x;</a>', '<a>'] as $xml) {
        $file = xmlAttachment();
        file_put_contents($file->getRealPath(), $xml);
        expect(fn () => $validator->execute($file->getRealPath(), 'xml'))->toThrow(RuntimeException::class);
    }
    foreach (['../invoice.xml', 'nested.zip', 'evil.svg'] as $name) {
        $file = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);
        $zip->addFromString($name, '<invoice/>');
        $zip->close();
        try {
            expect(fn () => $validator->execute($file, 'zip'))->toThrow(RuntimeException::class);
        } finally {
            unlink($file);
        }
    }
});

it('reports unsupported image processing clearly', function (): void {
    if (extension_loaded('imagick')) {
        $this->markTestSkipped('Only checks missing Imagick error.');
    }
    expect(fn () => app(ConvertAttachmentImage::class)->execute('/missing', '/missing'))->toThrow(RuntimeException::class, 'El procesamiento de imágenes');
});

it('validates ordinary archives and rejects actual expansion limits and encrypted entries', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'zip');
    try {
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);
        $zip->addFromString('invoice.xml', '<invoice/>');
        $zip->close();
        expect(app(ValidateAttachmentContent::class)->execute($file, 'zip'))->toBe('application/zip');
        config(['transaction_attachments.zip_max_expanded_bytes' => 3]);
        expect(fn () => app(ValidateAttachmentContent::class)->execute($file, 'zip'))->toThrow(RuntimeException::class);
        config(['transaction_attachments.zip_max_expanded_bytes' => 20971520]);
        $zip->open($file);
        $zip->setEncryptionName('invoice.xml', ZipArchive::EM_AES_256, 'password');
        $zip->close();
        expect(fn () => app(ValidateAttachmentContent::class)->execute($file, 'zip'))->toThrow(RuntimeException::class);
    } finally {
        unlink($file);
    }
});

it('bounds image dimensions before decoding and converts HEIC to WebP without metadata', function (): void {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick conversion is validated in the production image.');
    }
    $source = tempnam(sys_get_temp_dir(), 'image');
    $output = tempnam(sys_get_temp_dir(), 'webp');
    try {
        $image = new Imagick();
        $image->newImage(50, 25, 'white', 'png');
        $image->writeImage($source);
        config(['transaction_attachments.max_image_pixels' => 100]);
        expect(fn () => app(ConvertAttachmentImage::class)->execute($source, $output))->toThrow(RuntimeException::class, 'dimensiones');
        config(['transaction_attachments.max_image_pixels' => 40000000, 'transaction_attachments.image_max_dimension' => 20]);
        $dimensions = app(ConvertAttachmentImage::class)->execute($source, $output);
        expect($dimensions['width'])->toBe(20)->and($dimensions['height'])->toBe(10);
        $result = new Imagick($output);
        expect($result->getImageFormat())->toBe('WEBP')->and($result->getImageProfiles())->toBe([]);
        $result->clear();
        $image->clear();
        file_put_contents($source, base64_decode('AAAAHGZ0eXBoZWl4AAAAAG1pZjFoZWl4bWlhZgAAAWhtZXRhAAAAAAAAACFoZGxyAAAAAAAAAABwaWN0AAAAAAAAAAAAAAAAAAAAACJpbG9jAAAAAERAAAEAAQAAAAABjAABAAAAAAAAABYAAAAjaWluZgAAAAAAAQAAABVpbmZlAgAAAAABAABodmMxAAAAAA5waXRtAAAAAAABAAAA6GlwcnAAAADJaXBjbwAAAHVodmNDAQQIAAAAAAAAAAAAHvAA/P38/AAADwNgAAEAF0ABDAH//wQIAAADAJm4AAADAAAeugJAYQABACpCAQEECAAAAwCZuAAAAwAAHqAggQRSlurkprm4CGgwIAAAAwMgAAADACFiAAEABkQBwXPAiQAAABRpc3BlAAAAAAAAAEAAAABAAAAAKGNsYXAAAAAgAAAAAQAAACAAAAAB////4AAAAAL////gAAAAAgAAABBwaXhpAAAAAAMMDAwAAAAXaXBtYQAAAAAAAAABAAEEgQIEgwAAAB5tZGF0AAAAEigBrxOA9sGng6h/hVYKI6qAcA=='));
        $heicDimensions = app(ConvertAttachmentImage::class)->execute($source, $output);
        expect($heicDimensions['width'])->toBeLessThanOrEqual(20)->and((new finfo(FILEINFO_MIME_TYPE))->file($output))->toBe('image/webp');
    } finally {
        unlink($source);
        unlink($output);
    }
});

it('applies EXIF rotation before stripping image metadata', function (): void {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick conversion is validated in the production image.');
    }
    $source = tempnam(sys_get_temp_dir(), 'oriented');
    $output = tempnam(sys_get_temp_dir(), 'webp');
    try {
        $image = new Imagick();
        $image->newImage(40, 20, 'white', 'jpeg');
        $image->setImageOrientation(Imagick::ORIENTATION_RIGHTTOP);
        $image->setImageProfile('exif', hex2bin('45786966000049492a0008000000010012010300010000000600000000000000'));
        $image->writeImage($source);
        $image->clear();
        $dimensions = app(ConvertAttachmentImage::class)->execute($source, $output);
        expect($dimensions)->toBe(['width' => 20, 'height' => 40]);
        $result = new Imagick($output);
        expect($result->getImageProfiles())->toBe([]);
        $result->clear();
    } finally {
        unlink($source);
        unlink($output);
    }
});

it('marks exhausted jobs failed while preserving originals and recovers orphaned output cleanup', function (): void {
    $attachment = app(AcceptTransactionAttachment::class)->execute($this->transaction, $this->owner->id, xmlAttachment(), (string) Str::uuid());
    (new ProcessTransactionAttachment($attachment->id))->failed(new RuntimeException('private path'));
    expect($attachment->fresh()->status)->toBe('failed')->and($attachment->fresh()->error_message)->not->toContain('private path');
    app(ProcessAttachment::class)->execute($attachment->id);
    $disk = Storage::disk('transaction_attachments');
    $disk->put('sources/leftover', '<invoice/>');
    $attachment->refresh()->update(['source_path' => 'sources/leftover', 'reserved_bytes' => $attachment->size_bytes + 10]);
    app(MaintainAttachments::class)->execute();
    expect($attachment->fresh()->source_path)->toBeNull();
    $disk->assertMissing('sources/leftover');
});
