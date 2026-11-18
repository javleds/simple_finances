<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Rules\InvitableEmail;
use Illuminate\Foundation\Http\FormRequest;

class AccountRelationInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'      => ['sometimes', 'required', 'string', 'email', 'max:255', new InvitableEmail()],
            'percentage' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'status'     => ['sometimes', 'required', 'in:pending,accepted,declined'],
        ];
    }
}
