<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('qr.*', 'pickup.public.*', 'delivery.public.*')) {
            return $next($request);
        }
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $account = $user->fresh();
        $session = $request->session();
        $now = now()->getTimestamp();
        $lastActivity = (int) $session->get('staff.last_activity_at', 0);
        $startedAt = (int) $session->get('staff.started_at', 0);
        $expired = $now - $lastActivity >= config('staff.idle_timeout_minutes') * 60
            || $now - $startedAt >= config('staff.absolute_timeout_hours') * 3600;

        if (! $account || ! $account->isActiveStaff()
            || $session->get('staff.auth_version') !== $account->auth_version
            || $expired) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Sessão expirada. Entre novamente.'], 401)
                : redirect()->route('login');
        }

        Auth::guard('web')->setUser($account);
        $session->put('staff.last_activity_at', $now);

        return $next($request);
    }
}
