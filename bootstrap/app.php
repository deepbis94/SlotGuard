<?php

use App\Exceptions\AlreadyCancelledException;
use App\Exceptions\BookingOverlapException;
use App\Exceptions\CancellationWindowException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (BookingOverlapException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [],
            ], 409);
        });

        $exceptions->render(function (CancellationWindowException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [],
            ], 409);
        });

        $exceptions->render(function (AlreadyCancelledException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [],
            ], 422);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'HTTP error.',
                'errors' => [],
            ], $e->getStatusCode());
        });
    })->create();
