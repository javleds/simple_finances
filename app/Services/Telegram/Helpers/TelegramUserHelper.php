<?php

namespace App\Services\Telegram\Helpers;

use App\Models\User;

class TelegramUserHelper
{
    public static function getChatId(array $telegramUpdate): ?string
    {
        return data_get($telegramUpdate, 'message.chat.id');
    }

    public static function getTelegramUserId(array $telegramUpdate): ?int
    {
        return data_get($telegramUpdate, 'message.from.id');
    }

    public static function getAuthenticatedUser(array $telegramUpdate): ?User
    {
        $chatId = self::getChatId($telegramUpdate);

        if (!$chatId) {
            return null;
        }

        return User::where('telegram_chat_id', $chatId)->first();
    }

    public static function isUserAuthenticated(array $telegramUpdate): bool
    {
        return !is_null(self::getAuthenticatedUser($telegramUpdate));
    }
}
