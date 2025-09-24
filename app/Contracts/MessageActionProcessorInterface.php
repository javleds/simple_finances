<?php

namespace App\Contracts;

use App\Enums\MessageAction;
use App\Models\User;

interface MessageActionProcessorInterface
{
    /**
     * Tipo de acción que puede procesar este procesador
     */
    public static function getActionType(): MessageAction;

    /**
     * Determina si este procesador puede manejar la acción dada
     */
    public function canHandle(MessageAction $action, array $context = []): bool;

    /**
     * Procesa la acción del mensaje y devuelve la respuesta
     */
    public function process(array $context, User $user): string;

    /**
     * Prioridad del procesador (mayor número = mayor prioridad)
     * Útil cuando múltiples procesadores pueden manejar la misma acción
     */
    public function getPriority(): int;
}