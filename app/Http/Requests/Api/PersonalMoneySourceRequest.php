<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PersonalMoneySourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', 'in:transaction,reimbursement'],
            'source_id'   => ['required', 'integer', 'min:1'],
            'resolution'  => ['required', 'in:personal,external,pending'],
        ];
    }
}
