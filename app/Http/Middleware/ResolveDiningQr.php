<?php

namespace App\Http\Middleware;

use App\Application\Dining\GuestGrant;
use App\Models\DiningTable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveDiningQr
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');
        $table = DiningTable::query()->where('qr_hash', hash('sha256', $token))->where('is_active', true)->firstOrFail();
        $key = 'dining.grants.'.$table->id;
        $stored = $request->session()->get($key);
        if ($request->routeIs('qr.menu')) {
            $visitor = is_array($stored) && ($stored['expires'] ?? 0) > now()->timestamp ? $stored['visitor'] : (string) Str::uuid();
            $stored = ['visitor' => $visitor, 'version' => $table->qr_version, 'cycle' => $table->cycle,
                'expires' => now()->addMinutes((int) config('dining.grant_minutes'))->timestamp];
            $request->session()->put($key, $stored);
        }
        abort_unless(is_array($stored) && isset($stored['visitor'], $stored['version'], $stored['cycle'], $stored['expires']), 403);
        $grant = new GuestGrant($table->id, $stored['version'], $stored['cycle'], $stored['visitor'], $stored['expires']);
        $grant->authorize($table);
        $request->attributes->set('dining.table', $table);
        $request->attributes->set('dining.grant', $grant);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
