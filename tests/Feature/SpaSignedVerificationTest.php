<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

it('accepts verification links signed for either application domain', function (string $domain): void {
    config()->set('app.spa_url', 'https://fin-si.com');
    config()->set('app.url', 'https://fin-si.com');

    $user = User::factory()->unverified()->create();
    URL::forceRootUrl($domain);
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
            'redirect_to_spa' => 1,
        ],
    );
    URL::forceRootUrl('https://fin-si.com');

    $this->get($verificationUrl)
        ->assertRedirect('https://fin-si.com/email-verification?status=verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
})->with(['https://api.finsi.com', 'https://fin-si.com']);
