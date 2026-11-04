<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Chat;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'            => ['prohibited'],
            'actor_user_id'      => ['prohibited'],
            'channel'            => ['prohibited'],
            'client_request_id'  => [$this->isMethod('POST') && ! $this->routeIs('chat.cancel') ? 'required' : 'sometimes', 'uuid'],
            'message'            => [$this->routeIs('chat.runs') ? 'required' : 'prohibited', 'string', 'max:4000'],
            'context'            => [$this->routeIs('chat.runs') ? 'required' : 'prohibited', 'array:scope,account_id,route_name'],
            'context.scope'      => ['required_with:context', 'in:global,account'],
            'context.account_id' => ['required_if:context.scope,account', 'nullable', 'string', 'regex:/^[1-9][0-9]*$/', 'max:18', 'prohibited_if:context.scope,global'],
            'context.route_name' => ['nullable', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'cursor'             => ['sometimes', 'string', 'max:200'],
            'after'              => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
