<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function signIn(User $user): static
    {
        $this->actingAs($user);
        $this->withSession([
            'staff.auth_version' => $user->auth_version,
            'staff.started_at' => now()->timestamp,
            'staff.last_activity_at' => now()->timestamp,
        ]);

        return $this;
    }
}
