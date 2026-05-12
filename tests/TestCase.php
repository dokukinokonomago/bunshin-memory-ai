<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function withApiToken(User $user, string $name = 'feature-test'): static
    {
        return $this->withHeader(
            'Authorization',
            'Bearer '.$user->createApiToken($name)->plainTextToken,
        );
    }
}
