<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Deliverability;
use App\Services\Settings;
use App\Services\WebhookParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public const PROVIDERS = ['postmark', 'mailgun', 'ses', 'sendgrid', 'resend', 'brevo', 'generic'];

    /** Bounce/complaint webhook. The secret in the URL authenticates the provider. */
    public function handle(Request $request, string $provider, string $secret, WebhookParser $parser, Deliverability $deliverability, Settings $settings): JsonResponse
    {
        $expected = (string) $settings->get('webhook_secret');
        abort_unless($expected !== '' && hash_equals($expected, $secret) && in_array($provider, self::PROVIDERS, true), 404);

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'error' => 'invalid json'], 400);
        }
        // SES/SNS needs a one-time subscription confirmation; we never fetch URLs from requests (SSRF).
        if (($payload['Type'] ?? '') === 'SubscriptionConfirmation') {
            return response()->json(['ok' => true, 'note' => 'Confirm the subscription in the AWS console.']);
        }

        $handled = 0;
        foreach (array_slice($parser->parse($provider, $payload), 0, 1000) as $event) {
            $handled += $event['type'] === 'complaint'
                ? $deliverability->complaint($event['email'])
                : $deliverability->bounce($event['email'], $event['type'] === 'hard');
        }

        return response()->json(['ok' => true, 'handled' => $handled]);
    }
}
