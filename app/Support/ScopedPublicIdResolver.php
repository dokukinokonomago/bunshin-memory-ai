<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Memory;
use App\Models\TenantMemberInvitation;
use App\Models\User;

final class ScopedPublicIdResolver
{
    private const ULID_PATTERN = '[0-9A-HJKMNP-TV-Z]{26}';

    public static function normalizeIdentifier(mixed $identifier): mixed
    {
        return is_string($identifier) ? trim($identifier) : $identifier;
    }

    public static function isBlankIdentifier(mixed $identifier): bool
    {
        $identifier = self::normalizeIdentifier($identifier);

        return $identifier === null || $identifier === '';
    }

    public static function isCategoryIdentifier(mixed $identifier): bool
    {
        return self::isIdentifier($identifier, 'cat');
    }

    public static function isMemoryIdentifier(mixed $identifier): bool
    {
        return self::isIdentifier($identifier, 'mem');
    }

    public static function isUserIdentifier(mixed $identifier): bool
    {
        return self::isIdentifier($identifier, 'usr');
    }

    public static function isTenantMemberInvitationIdentifier(mixed $identifier): bool
    {
        return self::isIdentifier($identifier, 'inv');
    }

    public static function category(TenantUserContext $context, mixed $identifier): ?Category
    {
        $identifier = self::normalizeIdentifier($identifier);

        if (self::isPrefixedPublicId($identifier, 'cat')) {
            return Category::queryForContext($context)
                ->where('public_id', $identifier)
                ->first();
        }

        if (self::isPositiveIntegerIdentifier($identifier)) {
            return Category::queryForContext($context)
                ->whereKey((int) $identifier)
                ->first();
        }

        return null;
    }

    public static function memory(TenantUserContext $context, mixed $identifier): ?Memory
    {
        $identifier = self::normalizeIdentifier($identifier);

        if (self::isPrefixedPublicId($identifier, 'mem')) {
            return Memory::queryForContext($context)
                ->where('public_id', $identifier)
                ->first();
        }

        if (self::isPositiveIntegerIdentifier($identifier)) {
            return Memory::queryForContext($context)
                ->whereKey((int) $identifier)
                ->first();
        }

        return null;
    }

    public static function user(TenantUserContext $context, mixed $identifier): ?User
    {
        $identifier = self::normalizeIdentifier($identifier);

        if (self::isPrefixedPublicId($identifier, 'usr')) {
            return User::query()
                ->where('tenant_id', $context->tenantId())
                ->where('public_id', $identifier)
                ->first();
        }

        if (self::isPositiveIntegerIdentifier($identifier)) {
            return User::query()
                ->where('tenant_id', $context->tenantId())
                ->whereKey((int) $identifier)
                ->first();
        }

        return null;
    }

    public static function tenantMemberInvitation(TenantUserContext $context, mixed $identifier): ?TenantMemberInvitation
    {
        $identifier = self::normalizeIdentifier($identifier);

        if (self::isPrefixedPublicId($identifier, 'inv')) {
            return TenantMemberInvitation::query()
                ->where('tenant_id', $context->tenantId())
                ->where('public_id', $identifier)
                ->first();
        }

        if (self::isPositiveIntegerIdentifier($identifier)) {
            return TenantMemberInvitation::query()
                ->where('tenant_id', $context->tenantId())
                ->whereKey((int) $identifier)
                ->first();
        }

        return null;
    }

    private static function isIdentifier(mixed $identifier, string $prefix): bool
    {
        $identifier = self::normalizeIdentifier($identifier);

        return self::isPositiveIntegerIdentifier($identifier)
            || self::isPrefixedPublicId($identifier, $prefix);
    }

    private static function isPositiveIntegerIdentifier(mixed $identifier): bool
    {
        if (is_int($identifier)) {
            return $identifier > 0;
        }

        if (! is_string($identifier)) {
            return false;
        }

        return preg_match('/^[1-9][0-9]*$/', $identifier) === 1;
    }

    private static function isPrefixedPublicId(mixed $identifier, string $prefix): bool
    {
        if (! is_string($identifier)) {
            return false;
        }

        return preg_match('/^'.preg_quote($prefix, '/').'_'.self::ULID_PATTERN.'$/', $identifier) === 1;
    }
}
