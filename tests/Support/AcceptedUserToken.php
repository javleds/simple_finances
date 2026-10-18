<?php

declare(strict_types=1);

namespace Tests\Support;

use function app;

use App\Dto\Legal\LegalAcceptanceData;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use App\Services\Legal\GetLegalDocuments;
use App\Services\Legal\RecordLegalAcceptance;

class AcceptedUserToken
{
    public static function generate(User $user): array
    {
        $documents = app(GetLegalDocuments::class)->execute()['documents'];
        app(RecordLegalAcceptance::class)->execute($user, new LegalAcceptanceData(
            $documents['terms']['hash'], $documents['privacy']['hash'], true, true, true,
        ));

        return app(JwtTokenService::class)->generate($user);
    }
}
