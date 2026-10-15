<?php

use App\Console\Commands\PostDeploy;
use App\Models\Account;
use App\Models\AccountInvite;
use App\Models\User;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

it('removes obsolete Livewire compiled views before rendering invitation emails', function (): void {
    $directory = sys_get_temp_dir().'/fin-si-views-'.bin2hex(random_bytes(8));
    File::makeDirectory($directory);
    config()->set('view.compiled', $directory);

    try {
        $path = app('blade.compiler')->getCompiledPath(resource_path('views/mail/accounts/invite.blade.php'));
        File::put($path, '<?php new \\Livewire\\Mechanisms\\ExtendBlade\\ExtendBlade; ?>');
        touch($path, time() + 60);

        $invite = new AccountInvite(['percentage' => 25]);
        $invite->setRelation('user', new User(['name' => 'Account owner']));
        $invite->setRelation('account', new Account(['name' => 'Shared expenses']));
        $data = ['invite' => $invite, 'link' => 'https://example.test/register'];

        expect(fn () => app(Markdown::class)->render('mail.accounts.invite', $data))
            ->toThrow(\Illuminate\View\ViewException::class);

        $command = Mockery::mock(PostDeploy::class)->makePartial();
        $command->shouldReceive('option')->with('migrate')->andReturn(false);
        $command->shouldReceive('confirm')->andReturn(false);
        $command->shouldReceive('call')->with('view:clear')->once()
            ->andReturnUsing(fn (): int => Artisan::call('view:clear'));
        foreach (['config:cache', 'route:cache', 'event:cache'] as $step) {
            $command->shouldReceive('call')->with($step)->once()->andReturn(0);
        }

        expect($command->handle())->toBe(PostDeploy::SUCCESS)
            ->and(File::exists($path))->toBeFalse();

        $html = (string) app(Markdown::class)->render('mail.accounts.invite', $data);

        expect($html)->toContain('Account owner', 'Shared expenses', 'https://example.test/register');
    } finally {
        File::deleteDirectory($directory);
        Mockery::close();
    }
});
