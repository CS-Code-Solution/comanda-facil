<?php

namespace App\Http\Middleware;

use App\Application\Delivery\DeliveryGrant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveDeliveryGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        $stored = $request->session()->get('delivery.visitor');
        if ($request->routeIs('delivery.public.menu')) {
            $visitor = is_array($stored) && ($stored['expires'] ?? 0) > now()->timestamp ? $stored['visitor'] : (string) Str::uuid();
            $stored = ['visitor' => $visitor, 'expires' => now()->addHours(2)->timestamp];
            $request->session()->put('delivery.visitor', $stored);
        }
        abort_unless(is_array($stored) && isset($stored['visitor'], $stored['expires']), 403);
        $grant = new DeliveryGrant($stored['visitor'], $stored['expires']);
        $grant->authorize();
        $request->attributes->set('delivery.grant', $grant);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
