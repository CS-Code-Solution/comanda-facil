<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $pinHash;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->bothify('equipe_########'),
            'email' => null,
            'password' => Str::random(64),
            'pin_hash' => static::$pinHash ??= Hash::make('012345'),
            'auth_version' => 1,
            'role' => StaffRole::Waiter->value,
            'is_active' => true,
        ];
    }
}
