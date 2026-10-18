<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Legal;

use App\Dto\Legal\LegalAcceptanceData;
use Illuminate\Foundation\Http\FormRequest;

class LegalAcceptanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terms_accepted'          => ['required', 'accepted'],
            'privacy_policy_accepted' => ['required', 'accepted'],
            'is_adult'                => ['required', 'accepted'],
            'terms_hash'              => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'privacy_hash'            => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    public function acceptanceData(): LegalAcceptanceData
    {
        return new LegalAcceptanceData(
            $this->string('terms_hash')->toString(),
            $this->string('privacy_hash')->toString(),
            $this->boolean('terms_accepted'),
            $this->boolean('privacy_policy_accepted'),
            $this->boolean('is_adult'),
        );
    }
}
