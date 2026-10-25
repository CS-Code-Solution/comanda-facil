<?php

use App\Http\Middleware\EnsureActiveStaff;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->trimStrings(except: ['pin', 'pin_confirmation', 'current_pin']);

        $middleware->web(append: [
            EnsureActiveStaff::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['pin', 'pin_confirmation', 'current_pin']);
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->routeIs('qr.*') && ! $request->expectsJson() && in_array($response->getStatusCode(), [403, 404, 419, 429], true)) {
                $status = $response->getStatusCode();
                $page = Inertia::render('public/dining/problem', ['message' => match ($status) {
                    429 => 'Muitas tentativas. Aguarde um minuto antes de tentar novamente.',
                    404 => 'Este acesso não está disponível neste navegador ou o QR foi substituído.',
                    default => 'O acesso expirou ou a mesa mudou. Leia o QR impresso novamente ou procure a equipe.',
                }])->toResponse($request)->setStatusCode($status);
                $page->headers->set('Cache-Control', 'private, no-store');
                $page->headers->set('Referrer-Policy', 'no-referrer');
                if ($response->headers->has('Retry-After')) {
                    $page->headers->set('Retry-After', $response->headers->get('Retry-After'));
                }

                return $page;
            }
            if ($response->getStatusCode() === 403 && ! $request->expectsJson() && ! $request->is('api/*')) {
                return Inertia::render('errors/forbidden')->toResponse($request)->setStatusCode(403);
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
