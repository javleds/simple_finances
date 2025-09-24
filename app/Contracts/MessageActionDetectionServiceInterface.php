<?php

namespace App\Contracts;

use App\Enums\MessageAction;

interface MessageActionDetectionServiceInterface
{
    /**
     * Detecta la acción que el usuario quiere realizar basado en su mensaje
     */
    public function detectAction(string $text): array;

    /**
     * Verifica si el servicio está disponible
     */
    public function isAvailable(): bool;
}