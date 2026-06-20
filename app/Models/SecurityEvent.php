<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'user_id',
    'event_type',
    'outcome',
    'subject_email',
    'ip_address',
    'user_agent',
    'metadata',
    'created_at',
])]
class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_LOGIN = 'auth.login';

    public const TYPE_SIGNUP = 'auth.signup';

    public const TYPE_PASSWORD_RESET_REQUEST = 'auth.password_reset.request';

    public const TYPE_PASSWORD_RESET_COMPLETE = 'auth.password_reset.complete';

    public const TYPE_PASSWORD_CHANGE = 'auth.password_change';

    public const TYPE_TOKEN_LOGOUT = 'auth.token.logout';

    public const TYPE_TOKEN_REVOKE = 'auth.token.revoke';

    public const TYPE_TOKEN_REVOKE_ALL = 'auth.token.revoke_all';

    public const TYPE_TOKEN_ROTATE = 'auth.token.rotate';

    public const TYPE_PROFILE_UPDATE = 'auth.profile.update';

    public const TYPE_TENANT_INVITATION_ACCEPT = 'auth.tenant_invitation.accept';

    public const TYPE_TENANT_INVITATION_CREATE = 'auth.tenant_invitation.create';

    public const TYPE_TENANT_INVITATION_REVOKE = 'auth.tenant_invitation.revoke';

    public const TYPE_TENANT_MEMBER_ROLE_CHANGE = 'auth.tenant_member.role_change';

    public const TYPE_TENANT_MEMBER_REVOKE = 'auth.tenant_member.revoke';

    public const TYPE_EMAIL_VERIFICATION_REQUEST = 'auth.email_verification.request';

    public const TYPE_EMAIL_VERIFICATION_COMPLETE = 'auth.email_verification.complete';

    public const TYPE_EMAIL_CHANGE_REQUEST = 'auth.email_change.request';

    public const TYPE_EMAIL_CHANGE_COMPLETE = 'auth.email_change.complete';

    public const TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_REQUEST = 'auth.secret_unlock_password_recovery.request';

    public const TYPE_SECRET_UNLOCK_PASSWORD_RECOVERY_COMPLETE = 'auth.secret_unlock_password_recovery.complete';

    public const TYPE_SECRET_UNLOCK_PASSWORD_FORCED_ROTATION = 'auth.secret_unlock_password_forced_rotation';

    public const TYPE_SECRET_UNLOCK_PASSWORD_CHANGE = 'auth.secret_unlock_password.change';

    public const TYPE_ACCOUNT_STATUS_CHANGE = 'auth.account_status.change';

    public const TYPE_ACCOUNT_EXPORT_REQUEST = 'auth.account_export.request';

    public const TYPE_ACCOUNT_DELETE = 'auth.account.delete';

    public const TYPE_TENANT_EXPORT_REQUEST = 'auth.tenant_export.request';

    public const TYPE_TENANT_ARCHIVE = 'auth.tenant.archive';

    public const TYPE_TENANT_PURGE = 'auth.tenant.purge';

    public const TYPE_BILLING_CHECKOUT_SESSION_CREATE = 'billing.checkout_session.create';

    public const TYPE_BILLING_PORTAL_SESSION_CREATE = 'billing.portal_session.create';

    public const TYPE_BILLING_WEBHOOK_SYNC = 'billing.webhook.sync';

    public const TYPE_BILLING_RECONCILIATION = 'billing.reconciliation';

    public const TYPE_BILLING_SUBSCRIPTION_CANCEL_REQUEST = 'billing.subscription_cancel.request';

    public const TYPE_MEMORY_CREATE = 'memory.create';

    public const TYPE_MEMORY_UPDATE = 'memory.update';

    public const TYPE_MEMORY_DELETE = 'memory.delete';

    public const TYPE_CATEGORY_CREATE = 'category.create';

    public const TYPE_CATEGORY_UPDATE = 'category.update';

    public const TYPE_CATEGORY_DELETE = 'category.delete';

    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_FAILURE = 'failure';

    public const OUTCOME_REQUESTED = 'requested';

    public const OUTCOME_SKIPPED = 'skipped';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
