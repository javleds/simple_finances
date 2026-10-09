<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppVerificationCode extends Model
{
    protected $table = 'whatsapp_verification_codes';

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}
