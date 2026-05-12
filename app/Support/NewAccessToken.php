<?php

namespace App\Support;

use App\Models\PersonalAccessToken;

final readonly class NewAccessToken
{
    public function __construct(
        public PersonalAccessToken $accessToken,
        public string $plainTextToken,
    ) {}
}
