<?php

namespace Tests\Fixtures\Telegram;

class TelegramWebhookFixtures
{
    public static function textMessage(): array
    {
        return TextMessage::mock();
    }

    public static function voiceMessage(): array
    {
        return VoiceMessage::mock();
    }

    public static function photoMessage(): array
    {
        return PhotoMessage::mock();
    }

    public static function photoWithCaptionMessage(): array
    {
        return PhotoWithCaptionMessage::mock();
    }

    public static function videoMessage(): array
    {
        return VideoMessage::mock();
    }

    public static function allMessageTypes(): array
    {
        return [
            'text' => self::textMessage(),
            'voice' => self::voiceMessage(),
            'photo' => self::photoMessage(),
            'photo_with_caption' => self::photoWithCaptionMessage(),
            'video' => self::videoMessage(),
        ];
    }
}
