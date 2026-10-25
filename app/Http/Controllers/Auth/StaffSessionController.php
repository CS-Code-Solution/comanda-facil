<?php

namespace App\Http\Controllers\Auth;

use App\Application\Staff\AuthenticateStaff;
use App\Application\Staff\StaffCredentials;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/login', ['pinLength' => StaffCredentials::PIN_LENGTH]);
    }

    public function store(StaffLoginRequest $request, AuthenticateStaff $authenticate): RedirectResponse
    {
        $user = $authenticate->execute($request->string('username')->toString(), $request->string('pin')->toString());

        if (! $user) {
            event(new Failed('web', null, ['username' => $request->string('username')->toString()]));

            throw ValidationException::withMessages(['username' => 'Usuário ou PIN inválido.']);
        }

        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $request->session()->put([
            'staff.auth_version' => $user->auth_version,
            'staff.started_at' => now()->timestamp,
            'staff.last_activity_at' => now()->timestamp,
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
