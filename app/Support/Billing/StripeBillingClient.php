<?php

namespace App\Support\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class StripeBillingClient
{
    /**
     * @return array<string, mixed>
     */
    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->get('/v1/subscriptions/'.rawurlencode($subscriptionId));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelSubscriptionImmediately(string $subscriptionId): array
    {
        $response = $this->delete('/v1/subscriptions/'.rawurlencode($subscriptionId), [
            'invoice_now' => false,
            'prorate' => false,
        ]);

        if (($response['status'] ?? null) !== 'canceled') {
            throw new BillingProviderException('Billing provider did not confirm subscription cancellation.');
        }

        return $response;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCustomerSubscriptions(string $customerId, int $limit = 2): array
    {
        $response = $this->get('/v1/subscriptions', [
            'customer' => $customerId,
            'status' => 'all',
            'limit' => max(1, min(10, $limit)),
        ]);

        $subscriptions = $response['data'] ?? null;

        if (! is_array($subscriptions)) {
            throw new BillingProviderException('Billing provider returned an invalid subscription list.');
        }

        return array_values(array_filter(
            $subscriptions,
            static fn (mixed $subscription): bool => is_array($subscription),
        ));
    }

    public function createCustomer(Tenant $tenant, User $owner): string
    {
        $response = $this->post('/v1/customers', [
            'email' => $owner->email,
            'metadata[tenant_public_id]' => $tenant->public_id,
            'metadata[owner_user_public_id]' => $owner->public_id,
        ]);

        $customerId = $response['id'] ?? null;

        if (! is_string($customerId) || trim($customerId) === '') {
            throw new BillingProviderException('Billing provider did not return a customer id.');
        }

        return $customerId;
    }

    public function createCheckoutSession(Tenant $tenant, User $owner, string $customerId, string $priceId): string
    {
        $response = $this->post('/v1/checkout/sessions', [
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => $tenant->public_id,
            'success_url' => (string) config('bunshin.billing.checkout.success_url'),
            'cancel_url' => (string) config('bunshin.billing.checkout.cancel_url'),
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => 1,
            'metadata[tenant_public_id]' => $tenant->public_id,
            'metadata[owner_user_public_id]' => $owner->public_id,
            'subscription_data[metadata][tenant_public_id]' => $tenant->public_id,
            'subscription_data[metadata][owner_user_public_id]' => $owner->public_id,
        ]);

        return $this->urlFromResponse($response, 'checkout session');
    }

    public function createPortalSession(string $customerId): string
    {
        $response = $this->post('/v1/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => (string) config('bunshin.billing.portal.return_url'),
        ]);

        return $this->urlFromResponse($response, 'customer portal session');
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function post(string $path, array $form): array
    {
        $secretKey = (string) config('bunshin.billing.providers.stripe.secret_key');
        $baseUrl = rtrim((string) config('bunshin.billing.providers.stripe.api_base_url'), '/');

        try {
            $response = Http::asForm()
                ->withToken($secretKey)
                ->timeout(10)
                ->post($baseUrl.$path, $form)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            throw new BillingProviderException('Billing provider request failed.', previous: $exception);
        }

        if (! is_array($response)) {
            throw new BillingProviderException('Billing provider returned an invalid response.');
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $secretKey = (string) config('bunshin.billing.providers.stripe.secret_key');
        $baseUrl = rtrim((string) config('bunshin.billing.providers.stripe.api_base_url'), '/');

        try {
            $response = Http::withToken($secretKey)
                ->timeout(10)
                ->get($baseUrl.$path, $query)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            throw new BillingProviderException('Billing provider request failed.', previous: $exception);
        }

        if (! is_array($response)) {
            throw new BillingProviderException('Billing provider returned an invalid response.');
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function delete(string $path, array $form = []): array
    {
        $secretKey = (string) config('bunshin.billing.providers.stripe.secret_key');
        $baseUrl = rtrim((string) config('bunshin.billing.providers.stripe.api_base_url'), '/');

        try {
            $response = Http::asForm()
                ->withToken($secretKey)
                ->timeout(10)
                ->delete($baseUrl.$path, $form)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            throw new BillingProviderException('Billing provider request failed.', previous: $exception);
        }

        if (! is_array($response)) {
            throw new BillingProviderException('Billing provider returned an invalid response.');
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function urlFromResponse(array $response, string $resource): string
    {
        $url = $response['url'] ?? null;

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new BillingProviderException("Billing provider did not return a valid {$resource} URL.");
        }

        return $url;
    }
}
