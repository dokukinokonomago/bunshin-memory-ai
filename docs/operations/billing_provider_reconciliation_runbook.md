# Billing Provider Reconciliation Runbook

## Purpose

`bunshin:reconcile-billing-provider` compares the configured billing provider subscription state with local tenant billing fields after webhook outages, delayed provider delivery, or suspected drift.

The command is an internal operator tool. It is not a public API, not part of normal quota checks, and is not scheduled by default. Normal API runtime behavior continues to use local `tenants.plan_key`, `subscription_status`, `trial_ends_at`, and `subscription_ends_at` as the source of truth.

## Command

Default mode is dry-run. It reads provider subscription state, reports drift, and does not change local rows:

```sh
php artisan bunshin:reconcile-billing-provider --limit=100
```

Inspect one tenant by public id or slug:

```sh
php artisan bunshin:reconcile-billing-provider ten_01EXAMPLE
php artisan bunshin:reconcile-billing-provider tenant-slug
```

Apply safe local updates only after a dry-run has been reviewed:

```sh
php artisan bunshin:reconcile-billing-provider ten_01EXAMPLE --apply
```

`--limit` accepts `1` to `500` tenant billing records.

## Environment Variables

- `BUNSHIN_BILLING_ENABLED`: must be true.
- `BUNSHIN_BILLING_PROVIDER`: must be `stripe` for the current implementation.
- `BUNSHIN_STRIPE_SECRET_KEY`: provider API key used only in-memory for provider reads. Do not paste it into tickets, logs, or run output.
- `BUNSHIN_STRIPE_API_BASE_URL`: provider API base URL. Defaults to `https://api.stripe.com`.
- `BUNSHIN_STRIPE_PRO_PRICE_ID`: maps a provider price id to local `pro`. Unknown prices are not applied.

Webhook secret and checkout / portal URLs are not required for reconciliation reads.

## What Is Compared

The command inspects tenants with:

- `billing_provider` equal to the configured provider
- `billing_customer_id` present

For tenants with `billing_subscription_id`, it retrieves that subscription directly. For tenants that have a billing customer but no local subscription id, it lists that customer's subscriptions and only continues when exactly one provider subscription is found.

It maps only scrub-safe provider state into local comparison fields:

- provider subscription status to local `subscription_status`
- provider price id to local `plan_key` through configured price mapping
- cancellation-at-period-end flag
- trial end and subscription end timestamps
- local billing linkage fields

The command output shows tenant public id, slug, local plan/status, mapped provider plan/status, result, and changed field names. It must not print provider customer ids, subscription ids, price ids, raw payloads, billing addresses, card data, tax ids, raw customer emails, or provider API keys.

## Result Meanings

- `in_sync`: provider state maps to the existing local fields.
- `drift`: provider state is known and differs from local fields. Dry-run reports only; `--apply` can update it.
- `applied`: `--apply` updated the local tenant billing fields and wrote a scrub-safe `billing.reconciliation` security event.
- `provider_subscription_missing`: the provider has no single subscription to compare for this tenant.
- `provider_subscription_ambiguous`: the tenant has a customer id but no local subscription id, and the provider has multiple subscriptions. Resolve manually before applying.
- `customer_mismatch`: local customer linkage does not match the provider subscription customer.
- `tenant_archived`: local tenant is archived. Reconciliation must not reactivate it.
- `price_reference_missing` or `price_mapping_unknown`: the provider subscription cannot be mapped to a known local plan.
- `subscription_status_unknown`: provider status is not mapped to a local status.
- `provider_request_failed`: provider read failed or returned an invalid response. The command exits non-zero when any inspected tenant has this result.

## Apply Rules

`--apply` may update local fields only when all of these are true:

- tenant is not archived
- provider customer reference matches the local tenant customer
- provider subscription id is known
- provider price id maps to a configured local plan
- provider subscription status maps to a local status

Unknown references, unknown prices, unknown statuses, archived tenants, and ambiguous customer subscriptions are skipped and do not grant paid entitlements.

Apply mode writes one `billing.reconciliation` security event per updated tenant. Metadata is limited to provider, mode, result, local plan/status, and changed field names. It must not include provider ids, raw payloads, URLs, customer email, card data, billing address, tax id, or provider secrets.

## Recommended Workflow

1. Confirm the incident: missed webhook delivery, provider outage, application downtime, or support report.
2. Run dry-run for the suspected tenant first.
3. Verify the tenant public id / slug against approved internal context.
4. If dry-run reports `drift`, confirm the mapped provider plan/status is expected.
5. Run `--apply` for the single tenant.
6. Re-run dry-run for the tenant and confirm `in_sync`.
7. If multiple tenants were affected, run a broader dry-run and apply in small targeted batches.

Prefer replaying provider webhooks when the provider supports safe replay and event ordering is clear. Use reconciliation apply mode when webhook replay is not available, is incomplete, or would introduce a larger operational risk.

## Safety Rules

Do not paste or store the following in runbooks, tickets, alert annotations, chat, or incident notes:

- provider API keys or webhook signing secrets
- provider customer ids, subscription ids, checkout session ids, portal session ids, or price ids
- raw provider responses or webhook payloads
- card data, billing address, tax id, raw customer email
- memory titles, memory bodies, category names, tag names, or secret content
- account passwords, secret unlock passwords, invite tokens, reset tokens, Bearer tokens, signed URL secrets

Use tenant public ids, command result codes, mapped local plan/status, field names, timestamps, and aggregate counts instead.

## Failure Handling

1. If the command exits non-zero, identify whether the failure is config validation, unknown tenant target, provider request failure, or invalid provider response.
2. Confirm `BUNSHIN_BILLING_ENABLED`, `BUNSHIN_BILLING_PROVIDER`, `BUNSHIN_STRIPE_SECRET_KEY`, `BUNSHIN_STRIPE_API_BASE_URL`, and price mapping env vars.
3. Check application logs without copying provider secrets or raw provider response bodies into tickets.
4. Re-run dry-run for a single tenant.
5. Apply only after the dry-run result is understood.

If apply mode partially updates a batch, re-run dry-run. Already synced tenants should converge to `in_sync`; unresolved tenants should keep explicit skipped result codes.

## Related References

- Billing decision: `docs/decisions/0029-billing-provider-integration.md`
- Data model: `docs/architecture/data_model.md`
- API contract billing section: `docs/architecture/api_contract.md`
- Command: `app/Console/Commands/ReconcileBillingProviderCommand.php`
- Provider client: `app/Support/Billing/StripeBillingClient.php`
