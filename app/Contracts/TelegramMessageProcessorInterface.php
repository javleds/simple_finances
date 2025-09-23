<?php

namespace App\Contracts;

interface TelegramMessageProcessorInterface
{
    /**
     * Identificador único del tipo de mensaje que puede procesar este procesador
     */
    public static function getMessageType(): string;

    /**
     * Determina si este procesador puede manejar el mensaje dado
     */
    public function canHandle(array $telegramUpdate): bool;

    /**
     * Procesa el mensaje de Telegram y devuelve la respuesta
     */
    public function process(array $telegramUpdate): string;

    /**
     * Prioridad del procesador (mayor número = mayor prioridad)
     * Útil cuando múltiples procesadores pueden manejar el mismo mensaje
     */
    public function getPriority(): int;
}
