<?php

namespace Tests\Fixtures\Telegram;

class VoiceMessage
{
    public static function mock(): array
    {
        return [
            'update_id' => 123456790,
            'message' => [
                'message_id' => 2,
                'from' => [
                    'id' => 123456789,
                    'is_bot' => false,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'username' => 'johndoe',
                    'language_code' => 'es',
                ],
                'chat' => [
                    'id' => 123456789,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'username' => 'johndoe',
                    'type' => 'private',
                ],
                'date' => 1640995260,
                'voice' => [
                    'duration' => 3,
                    'mime_type' => 'audio/ogg',
                    'file_id' => 'AwACAgEAAxkBAAMLaNJFoSysfRq6Lp_yoRPqex3h0j8AAk8GAAJ9dJhG9Rge9oEmqHY2BA',
                    'file_unique_id' => 'AgADTwYAAn10mEY',
                    'file_size' => 13985,
                ],
            ],
        ];
    }
}
