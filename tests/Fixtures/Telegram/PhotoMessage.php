<?php

namespace Tests\Fixtures\Telegram;

class PhotoMessage
{
    public static function mock(): array
    {
        return [
            'update_id' => 123456791,
            'message' => [
                'message_id' => 3,
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
                'date' => 1640995320,
                'photo' => [
                    [
                        'file_id' => 'AgACAgEAAxkBAAMHaNJFVl4Il_wnCUOrMjNtwMDHzgwAAgELaxt9dJhGIeM8sa_a0NUBAAMCAANzAAM2BA',
                        'file_unique_id' => 'AQADAQtrG310mEZ4',
                        'file_size' => 1060,
                        'width' => 90,
                        'height' => 67
                    ],
                    [
                        'file_id' => 'AgACAgEAAxkBAAMHaNJFVl4Il_wnCUOrMjNtwMDHzgwAAgELaxt9dJhGIeM8sa_a0NUBAAMCAANtAAM2BA',
                        'file_unique_id' => 'AQADAQtrG310mEZy',
                        'file_size' => 16755,
                        'width' => 320,
                        'height' => 240
                    ],
                    [
                        'file_id' => 'AgACAgEAAxkBAAMHaNJFVl4Il_wnCUOrMjNtwMDHzgwAAgELaxt9dJhGIeM8sa_a0NUBAAMCAAN4AAM2BA',
                        'file_unique_id' => 'AQADAQtrG310mEZ9',
                        'file_size' => 94578,
                        'width' => 800,
                        'height' => 600
                    ],
                    [
                        'file_id' => 'AgACAgEAAxkBAAMHaNJFVl4Il_wnCUOrMjNtwMDHzgwAAgELaxt9dJhGIeM8sa_a0NUBAAMCAAN5AAM2BA',
                        'file_unique_id' => 'AQADAQtrG310mEZ-',
                        'file_size' => 135116,
                        'width' => 1280,
                        'height' => 960
                    ]
                ]
            ]
        ];
    }
}
