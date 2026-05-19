<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to enforce email verification on protected API routes.
 *
 * Usage in routes/api.php:
 *   Route::middleware(['auth:sanctum', 'verified.email'])->group(...)
 *
 * Register in bootstrap/app.php (Laravel 11) or Kernel.php (Laravel 10):
 *   'verified.email' => \App\Http\Middleware\EnsureEmailIsVerified::class,
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Your email address is not verified. Please check your inbox.',
                'email_verified' => false,
            ], 403);
        }

        return $next($request);
    }
}
