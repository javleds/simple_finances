<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Dto\Auth\RegisterUserData;
use App\Models\User;
use App\Services\Legal\RecordLegalAcceptance;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function __construct(private RecordLegalAcceptance $recordLegalAcceptance)
    {
    }

    public function execute(RegisterUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name'         => $data->name,
                'email'        => $data->email,
                'password'     => $data->password,
                'phone_number' => $data->phoneNumber,
            ]);
            $this->recordLegalAcceptance->execute($user, $data->legalAcceptance);

            return $user;
        });
    }
}
