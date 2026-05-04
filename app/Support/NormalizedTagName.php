<?php

namespace App\Support;

final readonly class NormalizedTagName
{
    public function __construct(
        public string $name,
        public string $normalizedName,
    ) {}
}
