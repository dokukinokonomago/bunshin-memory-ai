<?php

namespace App\Support;

final class TagNameNormalizer
{
    /**
     * Small deterministic alias table for early UI/API integration.
     *
     * @var array<string, string>
     */
    private const CANONICAL_NAMES = [
        'ともだち' => '友達',
        '友人' => '友達',
        '友達' => '友達',
        'なつ' => '夏',
        '夏' => '夏',
    ];

    public static function normalize(string $name): NormalizedTagName
    {
        $displayName = self::prepare($name, lowercase: false);
        $lookupName = self::prepare($name, lowercase: true);
        $canonicalName = self::CANONICAL_NAMES[$lookupName] ?? $displayName;

        return new NormalizedTagName(
            name: $canonicalName,
            normalizedName: self::prepare($canonicalName, lowercase: true),
        );
    }

    private static function prepare(string $name, bool $lowercase): string
    {
        $prepared = trim($name);

        if (function_exists('mb_convert_kana')) {
            $prepared = mb_convert_kana($prepared, 'asKV', 'UTF-8');
        }

        $prepared = preg_replace('/\s+/u', ' ', $prepared) ?? $prepared;
        $prepared = trim($prepared);

        if (! $lowercase) {
            return $prepared;
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($prepared, 'UTF-8');
        }

        return strtolower($prepared);
    }
}
