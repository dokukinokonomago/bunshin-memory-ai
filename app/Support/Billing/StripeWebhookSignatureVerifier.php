<?php

namespace App\Support\Billing;

use Illuminate\Http\Request;

class StripeWebhookSignatureVerifier
{
    public function hasValidSignature(Request $request, string $payload, string $secret): bool
    {
        $header = $request->headers->get('Stripe-Signature');

        if (! is_string($header) || trim($header) === '' || trim($secret) === '') {
            return false;
        }

        $signature = $this->parseSignatureHeader($header);
        $timestamp = $signature['timestamp'];

        if ($timestamp === null || $signature['signatures'] === []) {
            return false;
        }

        $tolerance = max(0, (int) config('bunshin.billing.webhook_tolerance_seconds', 300));

        if ($tolerance > 0 && abs(now()->getTimestamp() - $timestamp) > $tolerance) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signature['signatures'] as $providedSignature) {
            if (hash_equals($expected, $providedSignature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{timestamp: int|null, signatures: list<string>}
     */
    private function parseSignatureHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && is_string($value) && ctype_digit($value)) {
                $timestamp = (int) $value;
            }

            if ($key === 'v1' && is_string($value) && $value !== '') {
                $signatures[] = $value;
            }
        }

        return [
            'timestamp' => $timestamp,
            'signatures' => $signatures,
        ];
    }
}
