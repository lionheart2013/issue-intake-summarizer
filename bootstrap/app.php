<?php

use App\Support\ApiError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (): bool => true);

        $exceptions->render(fn (ValidationException $e): JsonResponse => ApiError::response(
            422,
            'validation_failed',
            'The given data was invalid.',
            $e->errors(),
        ));

        $exceptions->render(function (Throwable $e): JsonResponse {
            if (! $e instanceof HttpExceptionInterface) {
                return ApiError::response(500, 'server_error', 'Something went wrong on our side.');
            }

            return match ($e->getStatusCode()) {
                404 => ApiError::response(404, 'not_found', 'Resource not found.'),
                405 => ApiError::response(405, 'method_not_allowed', 'Method not allowed.'),
                default => ApiError::response($e->getStatusCode(), 'http_error', $e->getMessage() ?: 'Request failed.'),
            };
        });
    })->create();
