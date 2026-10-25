<?php

namespace App\Application\Staff;

class StaffCredentials
{
    public const PIN_LENGTH = 6;

    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    /** @return list<string> */
    public static function usernameRules(): array
    {
        return ['required', 'string', 'min:3', 'max:40', 'regex:/\A[a-z0-9][a-z0-9._-]*\z/'];
    }

    /** @return list<string> */
    public static function pinRules(): array
    {
        return ['required', 'string', 'regex:/\A[0-9]{'.self::PIN_LENGTH.'}\z/'];
    }
}
