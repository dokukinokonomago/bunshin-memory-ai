# 0020: Public ID Request Lookup Migration

Date: 2026-05-14

## Status

Accepted.

## Context

`tenants` / `users` / `categories` / `memories` now have prefixed ULID `public_id` values and core responses expose them alongside internal integer `id` values. Route parameters and request fields still use integer ids, so first-party frontends currently read integer ids from API responses and send those values back.

The next step is to make external requests use stable public identifiers without forcing a breaking frontend migration in one deploy.

## Decisions

- Internal integer primary keys remain the database identity and relation columns. `public_id` is the canonical external identifier for new client requests.
- During the v1 transition, eligible route parameters and request fields accept both prefixed public ids and positive integer ids. Prefixed public ids are preferred and all first-party frontends must migrate to them. Integer ids are legacy compatibility only and must not be used by new code.
- Response payloads keep integer `id` fields for the transition, but clients should treat them as deprecated display/debug fields. `public_id`, `parent_public_id`, `category_public_id`, and `user_public_id` are the fields to persist in frontend state.
- Route parameters for memories and categories resolve by prefixed id first:
  - `/api/v1/memories/{memory}` accepts `mem_01...` and, temporarily, numeric memory ids.
  - `/api/v1/categories/{category}` accepts `cat_01...` and, temporarily, numeric category ids.
  - Context mismatch, wrong prefix, malformed id, soft-deleted memory, or missing row returns `404 Not Found`.
- Category reference fields keep their current request names for v1 but switch canonical values to category public ids:
  - `category_id` in memory list, memory-space, memory create, and memory update accepts `cat_01...` as the preferred value and numeric category ids as deprecated compatibility.
  - `parent_id` in category create and update accepts `cat_01...` as the preferred root category value and numeric category ids as deprecated compatibility.
  - For list filters, a valid-looking category identifier outside the request context returns an empty result/aggregate, matching current filter behavior. Malformed filter values return `422`.
  - For write payloads, malformed values, wrong prefixes, missing categories, or outside-context categories return `422` validation errors on the submitted field.
- Tenant member route parameters use user public ids as the canonical value:
  - `/api/v1/tenant/members/{member}`, `/api/v1/tenant/members/{member}/role`, and `/api/v1/tenant/members/{member}/secret-unlock-password/force-rotation` accept `usr_01...` and, temporarily, numeric user ids.
  - Outside-tenant or missing members return `404`; role/self/owner boundary failures keep the existing `403` or `422` behavior.
- `tenant_member_invitations` use `inv_` public ids. `/api/v1/tenant/invitations/{invitation}` accepts `inv_01...` as the canonical route parameter and positive numeric ids only as v1 transition compatibility.
- The invite token itself remains an opaque credential, not the management resource identifier. New tokens may use `inv_...|plainTextToken` as lookup metadata, but clients must not derive route ids from the token string.
- Signed framework-style routes keep numeric user ids for now:
  - `/api/v1/auth/email/verify/{id}/{hash}`
  - `/api/v1/auth/email/change/verify/{id}/{hash}`
  - `/api/v1/secret-unlock-password/recovery/{id}/{hash}`
  These URLs are server-generated, signed, short-lived flows. They are not client-constructed lookup identifiers, so changing them is not part of this migration.
- Public ids are case-sensitive. Requests trim surrounding whitespace but do not lowercase or otherwise normalize public id values.

## Implementation Order

1. Add a reusable resolver for context-scoped memory/category/user public id lookup and tests for prefixed, numeric compatibility, wrong prefix, malformed value, and boundary failure. Done for memories / categories.
2. Move memories and categories APIs to the resolver: memory/category route params, `category_id`, `parent_id`, memory-space filter, and descendant lookup. Update the admin mockup and memory-space frontend to store and submit public ids. Done.
3. Move tenant member management routes to `usr_` lookup. Done.
4. Decide whether `tenant_member_invitations` need a public id column. Done in decision 0021 and implemented with `inv_` public ids.
5. After first-party frontends and external clients stop using integer ids, remove integer request compatibility in a future major version or explicit breaking-change task.

## Consequences

- Existing local mockups keep working during the transition and now submit memory/category public ids for memories and categories.
- Backend validation converts `category_id` / `parent_id` to internal ids before controllers persist them.
- API docs must distinguish internal integer ids from canonical external public ids until integer response fields are removed.

## Next Task

Account status management API is complete in decision 0022, account deletion / export scope plus self-service account export and deletion are complete in decision 0023, and tenant-wide export and tenant archive policy is complete in decision 0024. Tenant archive endpoint, tenant purge command, scheduler registration, and production runbook are implemented. Broader audit log / admin impersonation policy is decided in decision 0026, and broader audit logging is implemented in `security_events`. Tenant member invitation delivery email / notification is implemented. The account status admin mockup connection check is complete; the current mockup has no tenant members or account status path, so no wiring is needed now. Smoke-test-created data reference recheck was completed on 2026-05-16. The audit log pruning command retention and execution policy is decided in decision 0027. The `bunshin:prune-security-events` command, scheduler, schedule tests, and operations runbook are implemented. External logging/search integration is designed in decision 0028 and implementation is deferred. Billing provider integration scope and webhook handling is designed in decision 0029, and the provider-neutral data model, checkout / customer portal API, billing webhook receiver, provider-local reconciliation command / operations runbook, and tenant archive billing provider cancellation handling are implemented. The tenant archive billing cancellation failure triage runbook is complete. Decision 0031 defers a dedicated tenant archive billing cancellation retry command in v1.
