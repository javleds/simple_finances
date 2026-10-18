<?php

declare(strict_types=1);

namespace App\Services\Legal;

use App\Dto\Legal\LegalAcceptanceData;
use App\Models\LegalAcceptance;
use App\Models\User;

use function hash_equals;

use Illuminate\Validation\ValidationException;

use function now;

use Symfony\Component\HttpKernel\Exception\HttpException;

class RecordLegalAcceptance
{
    public function __construct(private GetLegalDocuments $documents)
    {
    }

    public function execute(User $user, LegalAcceptanceData $data): LegalAcceptance
    {
        if (! $data->termsAccepted || ! $data->privacyPolicyAccepted || ! $data->isAdult) {
            throw ValidationException::withMessages(['legal_acceptance' => 'Debes aceptar los documentos y declarar que tienes 18 años o más.']);
        }

        $documents = $this->documents->execute();

        if (! $documents['available']) {
            throw new HttpException(503, 'La información legal está temporalmente incompleta. Intenta más tarde.');
        }

        $terms = $documents['documents']['terms'];
        $privacy = $documents['documents']['privacy'];

        if (! hash_equals($terms['hash'], $data->termsHash) || ! hash_equals($privacy['hash'], $data->privacyHash)) {
            throw new HttpException(409, 'Los documentos cambiaron. Revisa la versión actual antes de aceptar.');
        }

        return LegalAcceptance::query()->firstOrCreate([
            'user_id'      => $user->id,
            'terms_hash'   => $terms['hash'],
            'privacy_hash' => $privacy['hash'],
        ], [
            'terms_version'           => $terms['version'],
            'privacy_version'         => $privacy['version'],
            'snapshot'                => $documents,
            'terms_accepted'          => true,
            'privacy_policy_accepted' => true,
            'financial_data_consent'  => true,
            'is_adult'                => true,
            'accepted_at'             => now(),
        ]);
    }
}
