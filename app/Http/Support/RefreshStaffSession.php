<?php

namespace App\Http\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefreshStaffSession
{
    public static function forOwnAccount(Request $request, User $account): void
    {
        if ($request->user()?->id === $account->id) {
            Auth::guard('web')->setUser($account);
            $request->session()->regenerate();
            $request->session()->put('staff.auth_version', $account->auth_version);
        }
    }
}
