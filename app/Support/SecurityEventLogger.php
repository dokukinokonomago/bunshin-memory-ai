<?php

namespace App\Support;

use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityEventLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        Request $request,
        string $eventType,
        string $outcome,
        ?Tenant $tenant = null,
        ?User $user = null,
        ?string $subjectEmail = null,
        array $metadata = [],
    ): SecurityEvent {
        $metadata = array_filter($metadata, static fn (mixed $value): bool => $value !== null);

        return SecurityEvent::query()->create([
            'tenant_id' => $tenant?->id ?? $user?->tenant_id,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'outcome' => $outcome,
            'subject_email' => $this->normalizeEmail($subjectEmail),
            'ip_address' => $request->ip(),
            'user_agent' => $this->userAgent($request),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = Str::lower(trim($email));

        return $email === '' ? null : $email;
    }

    private function userAgent(Request $request): ?string
    {
        $userAgent = $request->userAgent();

        if (! is_string($userAgent) || trim($userAgent) === '') {
            return null;
        }

        return Str::limit($userAgent, 512, '');
    }
}
