<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use LogicException;

final readonly class TenantUserContext
{
    private function __construct(
        private User $user,
        private Tenant $tenant,
    ) {}

    public static function fromUser(User $user): self
    {
        if ($user->getKey() === null) {
            throw new LogicException('Request user must be persisted before building a tenant context.');
        }

        if ($user->tenant_id === null) {
            throw new LogicException('Request user must have tenant_id before accessing tenant-scoped data.');
        }

        $tenant = $user->tenant;

        if (! $tenant instanceof Tenant) {
            throw new LogicException('Request user tenant could not be resolved.');
        }

        if ((int) $tenant->getKey() !== (int) $user->tenant_id) {
            throw new LogicException('Request user tenant relation does not match tenant_id.');
        }

        return new self($user, $tenant);
    }

    public function user(): User
    {
        return $this->user;
    }

    public function tenant(): Tenant
    {
        return $this->tenant;
    }

    public function userId(): int
    {
        return (int) $this->user->getKey();
    }

    public function tenantId(): int
    {
        return (int) $this->tenant->getKey();
    }
}
