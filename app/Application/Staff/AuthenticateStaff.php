<?php

namespace App\Application\Staff;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Timebox;

class AuthenticateStaff
{
    public function execute(string $username, #[\SensitiveParameter] string $pin): ?User
    {
        $username = StaffCredentials::normalizeUsername($username);

        if (Validator::make(['username' => $username, 'pin' => $pin], [
            'username' => StaffCredentials::usernameRules(),
            'pin' => StaffCredentials::pinRules(),
        ])->fails()) {
            return null;
        }

        return (new Timebox)->call(function () use ($username, $pin): ?User {
            $user = User::query()
                ->where('username', $username)
                ->first();

            if (! $user || ! $user->pin_hash || ! Hash::check($pin, $user->pin_hash)) {
                return null;
            }

            return $user->isActiveStaff() ? $user : null;
        }, 200000);
    }
}
