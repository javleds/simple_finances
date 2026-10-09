<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppConnection extends Model
{
    protected $table = 'whatsapp_connections';

    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime'];
    }
}
