<?php

namespace App\Http\Middleware;

use App\Application\Operations\OperationCorrelation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class OperationalRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlation = app(OperationCorrelation::class);
        $id = $correlation->id();
        $request->attributes->set('operation.request_id', $id);
        $started = hrtime(true);
        Log::withContext(['request_id' => $id]);
        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $id);
            if (app()->environment('testing') && preg_match('/^comanda_facil_pilot_[a-z0-9_]+$/', (string) config('database.connections.pgsql.database'))) {
                $response->headers->set('X-Pilot-Test', 'isolated');
            }
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
            $response->headers->set('X-Frame-Options', 'DENY');
            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
            }
            if ($response->getStatusCode() >= 500 || ($request->isMethod('POST') && $request->routeIs('qr.*', 'pickup.*', 'delivery.*', 'operations.*', 'cash.*'))) {
                // Route name only: QR secrets, UUID URLs, payloads, PIN, phone and address are never logged.
                Log::info('operation.http', ['route' => $request->route()?->getName(), 'method' => $request->method(),
                    'status' => $response->getStatusCode(), 'duration_ms' => (int) ((hrtime(true) - $started) / 1000000)]);
            }

            return $response;
        } finally {
            Log::withoutContext(['request_id']);
            $correlation->clear();
        }
    }
}
