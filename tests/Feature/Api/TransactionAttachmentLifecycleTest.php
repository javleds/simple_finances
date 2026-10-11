<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use App\Services\TransactionAttachments\AcceptTransactionAttachment;
use App\Services\TransactionAttachments\MaintainAttachments;
use App\Services\TransactionAttachments\ProcessAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('transaction_attachments');
    Queue::fake();
    config(['transaction_attachments.min_free_disk_bytes' => 0]);
    $this->author = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->author->id]);
    $this->account->users()->attach($this->author->id);
    $this->movement = Transaction::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->author->id]);
    $this->endpoint = '/api/transactions/'.$this->movement->id.'/attachments';
    $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.app(JwtTokenService::class)->generate($this->author)['token']]);
});

function lifecycleReceipt(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('receipt.xml', '<receipt amount="20"/>');
}

it('exposes configured public limits without storage paths or capacity details', function (): void {
    config(['transaction_attachments.max_files' => 7, 'transaction_attachments.max_file_bytes.xml' => 1234]);
    $this->getJson('/api/transaction-attachments/config')->assertOk()
        ->assertJsonPath('data.max_files', 7)->assertJsonPath('data.max_file_bytes.xml', 1234)
        ->assertJsonMissingPath('data.disk')->assertJsonMissingPath('data.max_storage_bytes');
});

it('rejects unsupported extensions empty content oversized files and transaction totals before queuing', function (): void {
    foreach ([UploadedFile::fake()->createWithContent('receipt.exe', 'MZbinary'), UploadedFile::fake()->createWithContent('empty.xml', '')] as $file) {
        $this->post($this->endpoint, ['file' => $file, 'upload_id' => (string) Str::uuid()])->assertStatus(422);
    }
    config(['transaction_attachments.max_file_bytes.xml' => 2]);
    $this->post($this->endpoint, ['file' => lifecycleReceipt(), 'upload_id' => (string) Str::uuid()])->assertStatus(422);
    config(['transaction_attachments.max_file_bytes.xml' => 1048576, 'transaction_attachments.max_transaction_bytes' => 3]);
    $this->post($this->endpoint, ['file' => lifecycleReceipt(), 'upload_id' => (string) Str::uuid()])->assertStatus(422);
    expect(TransactionAttachment::count())->toBe(0)->and(DB::table('transaction_attachment_capacity')->value('reserved_bytes'))->toBe(0);
    Queue::assertNothingPushed();
});

it('does not permit identifiers from another transaction even when both accounts belong to the requester', function (): void {
    $other = Transaction::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->author->id]);
    $attachment = app(AcceptTransactionAttachment::class)->execute($other, $this->author->id, lifecycleReceipt(), (string) Str::uuid());
    app(ProcessAttachment::class)->execute($attachment->id);
    $url = $this->endpoint.'/'.$attachment->id;
    $this->get($url.'/download')->assertNotFound();
    $this->postJson($url.'/retry')->assertNotFound();
    $this->deleteJson($url)->assertNotFound();
    expect($attachment->fresh()->deleted_at)->toBeNull();
});

it('revokes access to a soft deleted account while retaining the receipt', function (): void {
    $attachment = app(AcceptTransactionAttachment::class)->execute($this->movement, $this->author->id, lifecycleReceipt(), (string) Str::uuid());
    app(ProcessAttachment::class)->execute($attachment->id);
    $path = $attachment->fresh()->path;
    $this->account->delete();
    $this->getJson($this->endpoint)->assertNotFound();
    $this->get($this->endpoint.'/'.$attachment->id.'/download')->assertNotFound();
    app(MaintainAttachments::class)->execute();
    Storage::disk('transaction_attachments')->assertExists($path);
    expect($attachment->fresh())->not->toBeNull();
});

it('adopts the destination account membership when a movement changes account', function (): void {
    $oldMember = User::factory()->create();
    $newMember = User::factory()->create();
    $this->account->users()->attach($oldMember->id);
    $destination = Account::factory()->create(['user_id' => $this->author->id]);
    $destination->users()->attach([$this->author->id, $newMember->id]);
    $attachment = app(AcceptTransactionAttachment::class)->execute($this->movement, $this->author->id, lifecycleReceipt(), (string) Str::uuid());
    app(ProcessAttachment::class)->execute($attachment->id);
    $this->movement->update(['account_id' => $destination->id]);
    $this->withHeaders(['Authorization' => 'Bearer '.app(JwtTokenService::class)->generate($oldMember)['token']]);
    $this->get($this->endpoint.'/'.$attachment->id.'/download')->assertForbidden();
    $this->withHeaders(['Authorization' => 'Bearer '.app(JwtTokenService::class)->generate($newMember)['token']]);
    $this->get($this->endpoint.'/'.$attachment->id.'/download')->assertOk();
});

it('preserves orphan cleanup metadata when parent transactions are removed by database cascade', function (): void {
    $child = Transaction::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->author->id, 'parent_transaction_id' => $this->movement->id]);
    $attachment = app(AcceptTransactionAttachment::class)->execute($child, $this->author->id, lifecycleReceipt(), (string) Str::uuid());
    app(ProcessAttachment::class)->execute($attachment->id);
    $path = $attachment->fresh()->path;
    $this->movement->delete();
    expect($attachment->fresh()->transaction_id)->toBeNull();
    app(MaintainAttachments::class)->execute();
    Storage::disk('transaction_attachments')->assertMissing($path);
    expect(TransactionAttachment::find($attachment->id))->toBeNull()->and(DB::table('transaction_attachment_capacity')->value('reserved_bytes'))->toBe(0);
});

it('returns ready uploads with the same id after a lost response without recreating jobs', function (): void {
    $uploadId = (string) Str::uuid();
    $first = $this->post($this->endpoint, ['file' => lifecycleReceipt(), 'upload_id' => $uploadId])->assertStatus(202)->json('data.id');
    app(ProcessAttachment::class)->execute($first);
    $this->post($this->endpoint, ['file' => lifecycleReceipt(), 'upload_id' => $uploadId])->assertStatus(202)
        ->assertJsonPath('data.id', $first)->assertJsonPath('data.status', 'ready');
    expect(TransactionAttachment::count())->toBe(1);
});
