<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class TenantQuotaGuard
{
    public function __construct(private Tenant $tenant) {}

    public static function forTenant(Tenant $tenant): self
    {
        return new self($tenant);
    }

    /**
     * @throws HttpException
     * @throws ValidationException
     */
    public function ensureCanCreateMemory(): void
    {
        $this->ensureTenantHasActivePlan();
        $this->ensureBelowLimit(
            resource: 'memories',
            currentCount: $this->tenant->memories()->count(),
            limit: $this->tenant->memoryQuotaLimit(),
            message: 'Memory quota exceeded for the current plan.',
        );
    }

    /**
     * @throws HttpException
     * @throws ValidationException
     */
    public function ensureCanCreateCategory(): void
    {
        $this->ensureTenantHasActivePlan();
        $this->ensureBelowLimit(
            resource: 'categories',
            currentCount: $this->tenant->categories()->count(),
            limit: $this->tenant->categoryQuotaLimit(),
            message: 'Category quota exceeded for the current plan.',
        );
    }

    /**
     * @throws HttpException
     */
    private function ensureTenantHasActivePlan(): void
    {
        if ($this->tenant->hasActivePlan()) {
            return;
        }

        throw new HttpException(
            Response::HTTP_PAYMENT_REQUIRED,
            'Tenant subscription is not active.',
        );
    }

    /**
     * @throws ValidationException
     */
    private function ensureBelowLimit(string $resource, int $currentCount, ?int $limit, string $message): void
    {
        if ($limit === null || $currentCount < $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'quota' => [$message],
            $resource => [$message],
        ]);
    }
}
