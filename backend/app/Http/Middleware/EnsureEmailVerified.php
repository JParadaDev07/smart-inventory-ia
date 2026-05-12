<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.must_verify_email', false)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Please verify your email address to continue.',
            'code' => 'EMAIL_NOT_VERIFIED',
        ], 403);
    }
}
