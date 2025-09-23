<?php

namespace Tests\Fixtures\Telegram;

class VideoMessage
{
    public static function mock(): array
    {
        return [
            'update_id' => 123456793,
            'message' => [
                'message_id' => 5,
                'from' => [
                    'id' => 123456789,
                    'is_bot' => false,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'username' => 'johndoe',
                    'language_code' => 'es'
                ],
                'chat' => [
                    'id' => 123456789,
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'username' => 'johndoe',
                    'type' => 'private'
                ],
                'date' => 1640995440,
                'video' => [
                    'duration' => 3,
                    'width' => 464,
                    'height' => 848,
                    'file_name' => 'video.mp4',
                    'mime_type' => 'video/mp4',
                    'thumbnail' => [
                        'file_id' => 'AAMCAQADGQEAAxFo0kZDiG1fwxkDLlpsWCVr7AlSDgACUAYAAn10mEYxUI6XlS7yHQEAB20AAzYE',
                        'file_unique_id' => 'AQADUAYAAn10mEZy',
                        'file_size' => 24275,
                        'width' => 175,
                        'height' => 320
                    ],
                    'thumb' => [
                        'file_id' => 'AAMCAQADGQEAAxFo0kZDiG1fwxkDLlpsWCVr7AlSDgACUAYAAn10mEYxUI6XlS7yHQEAB20AAzYE',
                        'file_unique_id' => 'AQADUAYAAn10mEZy',
                        'file_size' => 24275,
                        'width' => 175,
                        'height' => 320
                    ],
                    'file_id' => 'BAACAgEAAxkBAAMRaNJGQ4htX8MZAy5abFgla-wJUg4AAlAGAAJ9dJhGMVCOl5Uu8h02BA',
                    'file_unique_id' => 'AgADUAYAAn10mEY',
                    'file_size' => 659549
                ]
            ]
        ];
    }
}
