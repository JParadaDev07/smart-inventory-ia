<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class EmailVerificationController extends Controller
{
    /**
     * Verify email via signed URL (called from frontend or redirect from email link).
     */
    public function verify(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['message' => 'Invalid or expired verification link.'], 403);
        }

        $user = \App\Models\User::findOrFail($request->route('id'));

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->verificationResponse(true);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->verificationResponse(false);
    }

    /**
     * Resend verification email (authenticated).
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }

    private function verificationResponse(bool $alreadyVerified): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $redirect = $frontendUrl . '/login?verified=1';

        if (request()->expectsJson()) {
            return response()->json([
                'message' => $alreadyVerified ? 'Email already verified.' : 'Email verified.',
                'redirect' => $redirect,
            ]);
        }

        return redirect()->away($redirect);
    }
}
