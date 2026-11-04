<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use function abort_unless;

use App\Http\Requests\Api\Chat\ChatRequest;
use App\Services\Chat\SendAgentRequest;

use function auth;
use function connection_aborted;

use FinSi\FinanceDomain\Queries\VisibleAccountsForUser;

use function flush;

use Illuminate\Http\JsonResponse;

use function ob_flush;
use function ob_get_level;
use function response;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends ApiController
{
    public function __construct(private readonly SendAgentRequest $agent)
    {
    }

    public function threads(ChatRequest $request): JsonResponse
    {
        return $this->forward($request, '/threads');
    }

    public function messages(ChatRequest $request, string $thread): JsonResponse
    {
        return $this->forward($request, '/threads/' . $thread . '/messages');
    }

    public function runs(ChatRequest $request, string $thread, VisibleAccountsForUser $accounts): JsonResponse
    {
        $accountId = $request->input('context.account_id');
        if (null !== $accountId) {
            abort_unless($accounts->query((int) auth()->id())->whereKey($accountId)->exists(), 404);
        }

        return $this->forward($request, '/threads/' . $thread . '/runs', 202);
    }

    public function run(ChatRequest $request, string $thread, string $run): JsonResponse
    {
        return $this->forward($request, '/threads/' . $thread . '/runs/' . $run);
    }

    public function cancel(ChatRequest $request, string $thread, string $run): JsonResponse
    {
        return $this->forward($request, '/threads/' . $thread . '/runs/' . $run . '/cancel');
    }

    public function events(ChatRequest $request, string $thread, string $run): StreamedResponse
    {
        $response = $this->agent->execute('GET', '/threads/' . $thread . '/runs/' . $run . '/events', (int) auth()->id(), $request->validated(), true);
        $body = $response->toPsrResponse()->getBody();

        return response()->stream(function () use ($body): void {
            try {
                while (! $body->eof() && ! connection_aborted()) {
                    echo $body->read(4096);
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } finally {
                $body->close();
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no']);
    }

    private function forward(ChatRequest $request, string $path, int $status = 200): JsonResponse
    {
        $response = $this->agent->execute($request->method(), $path, (int) auth()->id(), $request->validated());

        return $this->respond(['data' => $response->json()], $status);
    }
}
