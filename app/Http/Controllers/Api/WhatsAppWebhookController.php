<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Webhooks\ReceiveWhatsAppWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = (string) config('services.whatsapp.verify_token');
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $providedToken = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($token === '' || $mode !== 'subscribe' || ! is_string($providedToken)
            || ! hash_equals($token, $providedToken) || ! is_string($challenge)) {
            return response('Forbidden', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, ReceiveWhatsAppWebhook $receiver): Response
    {
        $secret = (string) config('services.whatsapp.app_secret');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');

        if ($secret === '' || $phoneNumberId === '') {
            return response('Webhook unavailable', 503);
        }

        $body = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256', '');

        if (! hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $signature)) {
            return response('Forbidden', 403);
        }

        $payload = json_decode($body, true);

        if (! is_array($payload) || Validator::make($payload, [
            'object' => ['required', 'in:whatsapp_business_account'],
            'entry' => ['required', 'array', 'min:1'],
            'entry.*.id' => ['required', 'string'],
            'entry.*.changes' => ['required', 'array', 'min:1'],
            'entry.*.changes.*.field' => ['required', 'string'],
            'entry.*.changes.*.value' => ['required', 'array'],
        ])->fails()) {
            return response('Invalid webhook', 422);
        }

        foreach ($payload['entry'] as $entry) {
            foreach ($entry['changes'] as $change) {
                if ($change['field'] === 'messages'
                    && ($change['value']['metadata']['phone_number_id'] ?? null) !== $phoneNumberId) {
                    return response('Invalid phone number', 422);
                }
            }
        }

        try {
            $receiver->handle($body, $payload);
        } catch (Throwable) {
            return response('Webhook unavailable', 503);
        }

        return response('OK', 200);
    }
}
