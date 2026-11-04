<?php

declare(strict_types=1);

namespace App\Services\Chat;

use function abort;
use function abort_unless;
use function config;
use function filled;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

use function in_array;
use function rtrim;

class SendAgentRequest
{
    public function execute(string $method, string $path, int $actorId, array $payload = [], bool $stream = false): Response
    {
        abort_unless(config('chat.enabled') && filled(config('chat.token')), 503, 'El asistente no está disponible.');
        $identity = ['actor_user_id' => $actorId, 'channel' => 'web'];

        try {
            $response = Http::baseUrl(rtrim((string) config('chat.url'), '/') . '/internal/v1')
                ->withToken((string) config('chat.token'))
                ->accept($stream ? 'text/event-stream' : 'application/json')
                ->connectTimeout(5)->timeout($stream ? 150 : 15)
                ->withOptions(['stream' => $stream, 'allow_redirects' => false])
                ->send($method, $path, ['GET' === $method ? 'query' : 'json' => [...$payload, ...$identity]]);
        } catch (ConnectionException) {
            abort(503, 'No se pudo conectar con el asistente.');
        }
        abort_unless($response->successful(), in_array($response->status(), [403, 404, 409, 422, 429], true) ? $response->status() : 502, 'No se pudo completar la solicitud del asistente.');

        return $response;
    }
}
