<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class IssueAdminTokenCommand extends Command
{
    protected $signature = 'bunshin:issue-admin-token
        {--tenant=default : Tenant slug to create or reuse}
        {--tenant-name= : Tenant name used when creating a tenant}
        {--email=admin@example.test : Admin user email}
        {--name=Admin User : Admin user name used when creating a user}
        {--token-name=admin-mockup : Personal access token name}
        {--expires-days=30 : Token lifetime in days}';

    protected $description = 'Issue a Bearer token for the admin UI mockup without exposing a token issuance API.';

    public function handle(): int
    {
        $input = $this->normalizedOptions();
        $validator = Validator::make($input, [
            'tenant' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'tenant_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'token_name' => ['required', 'string', 'max:255'],
            'expires_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ], attributes: [
            'tenant_name' => 'tenant-name',
            'token_name' => 'token-name',
            'expires_days' => 'expires-days',
        ]);

        if ($validator->fails()) {
            $this->error('Invalid options.');

            foreach ($validator->errors()->all() as $error) {
                $this->line('- '.$error);
            }

            return self::FAILURE;
        }

        $existingUser = User::query()
            ->with('tenant')
            ->where('email', $input['email'])
            ->first();

        if ($existingUser instanceof User
            && $existingUser->tenant !== null
            && $existingUser->tenant->slug !== $input['tenant']
        ) {
            $this->error(sprintf(
                'User %s already belongs to tenant %s. Use that tenant or choose another email.',
                $input['email'],
                $existingUser->tenant->slug,
            ));

            return self::FAILURE;
        }

        $result = DB::transaction(function () use ($input, $existingUser): array {
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => $input['tenant']],
                ['name' => $input['tenant_name']],
            );

            if ($existingUser instanceof User) {
                $user = $existingUser->fresh();

                if ($user->tenant_id === null) {
                    $user->tenant()->associate($tenant);
                    $user->save();
                }

                $userCreated = false;
            } else {
                $user = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => Str::random(48),
                ]);
                $userCreated = true;
            }

            $revokedCount = $user->personalAccessTokens()
                ->where('name', $input['token_name'])
                ->delete();

            $expiresAt = now()->addDays((int) $input['expires_days']);
            $newAccessToken = $user->createApiToken(
                name: $input['token_name'],
                expiresAt: $expiresAt,
            );

            return [
                'tenant' => $tenant,
                'tenant_created' => $tenant->wasRecentlyCreated,
                'user' => $user,
                'user_created' => $userCreated,
                'revoked_count' => $revokedCount,
                'expires_at' => $expiresAt,
                'plain_text_token' => $newAccessToken->plainTextToken,
                'token_name' => $input['token_name'],
            ];
        });

        $this->info('Admin API token issued.');
        $this->line(sprintf(
            'Tenant: %s (#%d, %s)',
            $result['tenant']->slug,
            $result['tenant']->id,
            $result['tenant_created'] ? 'created' : 'existing',
        ));
        $this->line(sprintf(
            'User: %s (#%d, %s)',
            $result['user']->email,
            $result['user']->id,
            $result['user_created'] ? 'created' : 'existing',
        ));
        $this->line('Token name: '.$result['token_name']);
        $this->line('Revoked existing tokens: '.$result['revoked_count']);
        $this->line('Expires at: '.$result['expires_at']->toIso8601String());
        $this->newLine();
        $this->warn('Bearer token is shown once. Store it in the admin mockup Settings.');
        $this->line('Bearer token: '.$result['plain_text_token']);

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     tenant: string,
     *     tenant_name: string,
     *     email: string,
     *     name: string,
     *     token_name: string,
     *     expires_days: string
     * }
     */
    private function normalizedOptions(): array
    {
        $tenant = Str::lower($this->optionString('tenant', 'default'));
        $tenantName = $this->optionString('tenant-name', $this->defaultTenantName($tenant));

        return [
            'tenant' => $tenant,
            'tenant_name' => $tenantName,
            'email' => Str::lower($this->optionString('email', 'admin@example.test')),
            'name' => $this->optionString('name', 'Admin User'),
            'token_name' => $this->optionString('token-name', 'admin-mockup'),
            'expires_days' => $this->optionString('expires-days', '30'),
        ];
    }

    private function optionString(string $name, string $default): string
    {
        $value = $this->option($name);
        $value = is_array($value) ? reset($value) : $value;
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : $default;
    }

    private function defaultTenantName(string $tenant): string
    {
        return Str::title(str_replace(['-', '_'], ' ', $tenant));
    }
}
