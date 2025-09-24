<?php

namespace App\Contracts;

interface OpenAIServiceInterface
{
    /**
     * Procesa texto plano y extrae información de transacción
     */
    public function processText(string $text): array;

    /**
     * Procesa una imagen usando OCR y extrae información de transacción
     */
    public function processImage(string $imagePath): array;

    /**
     * Procesa audio usando transcripción y extrae información de transacción
     */
    public function processAudio(string $audioPath): array;

    /**
     * Transcribe un archivo de audio a texto
     */
    public function transcribeAudio(string $audioPath): array;

    /**
     * Detecta la acción que el usuario quiere realizar basado en su mensaje
     */
    public function detectMessageAction(string $text): array;
}
