<?php

namespace App\Models;

use App\Models\Concerns\HasPrefixedPublicId;
use App\Support\NewAccessToken;
use Database\Factories\UserFactory;
use DateTimeInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable(['tenant_id', 'role', 'account_status', 'name', 'email', 'pending_email', 'pending_email_requested_at', 'password', 'secret_unlock_password', 'deleted_at', 'anonymized_at'])]
#[Hidden(['password', 'secret_unlock_password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPrefixedPublicId, Notifiable;

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_ADMIN,
        self::ROLE_MEMBER,
    ];

    public const ACCOUNT_STATUS_ACTIVE = 'active';

    public const ACCOUNT_STATUS_DISABLED = 'disabled';

    public const ACCOUNT_STATUS_SUSPENDED = 'suspended';

    public const ACCOUNT_STATUSES = [
        self::ACCOUNT_STATUS_ACTIVE,
        self::ACCOUNT_STATUS_DISABLED,
        self::ACCOUNT_STATUS_SUSPENDED,
    ];

    public const TENANT_MANAGER_ROLES = [
        self::ROLE_OWNER,
        self::ROLE_ADMIN,
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'account_status' => self::ACCOUNT_STATUS_ACTIVE,
    ];

    protected static function publicIdPrefix(): string
    {
        return 'usr';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pending_email_requested_at' => 'datetime',
            'deleted_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'password' => 'hashed',
            'secret_unlock_password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Memory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class, 'owner_user_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'owner_user_id');
    }

    /**
     * @return MorphMany<PersonalAccessToken, $this>
     */
    public function personalAccessTokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    /**
     * @return HasMany<SecretUnlockToken, $this>
     */
    public function secretUnlockTokens(): HasMany
    {
        return $this->hasMany(SecretUnlockToken::class);
    }

    /**
     * @return HasMany<SecurityEvent, $this>
     */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function isTenantOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canManageTenantMembers(): bool
    {
        return in_array($this->role, self::TENANT_MANAGER_ROLES, true);
    }

    public function hasActiveAccount(): bool
    {
        return $this->account_status === self::ACCOUNT_STATUS_ACTIVE;
    }

    public function canAccessApi(): bool
    {
        if (! $this->hasActiveAccount()) {
            return false;
        }

        if ($this->tenant_id === null) {
            return true;
        }

        $tenant = $this->relationLoaded('tenant')
            ? $this->tenant
            : $this->tenant()->first();

        return $tenant instanceof Tenant && ! $tenant->isArchived();
    }

    public function hasSecretUnlockPassword(): bool
    {
        return is_string($this->secret_unlock_password) && $this->secret_unlock_password !== '';
    }

    public function checkSecretUnlockPassword(string $password): bool
    {
        return $this->hasSecretUnlockPassword()
            && Hash::check($password, (string) $this->secret_unlock_password);
    }

    /**
     * @param  list<string>  $abilities
     */
    public function createApiToken(
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null,
    ): NewAccessToken {
        $plainTextToken = Str::random(40);

        $accessToken = $this->personalAccessTokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new NewAccessToken(
            accessToken: $accessToken,
            plainTextToken: $accessToken->getKey().'|'.$plainTextToken,
        );
    }
}
