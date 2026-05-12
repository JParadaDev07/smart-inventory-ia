<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\RegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private RegisterService $registerService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerService->register($request->validated());
        $user->load('business.subscription');
        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => $this->userResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        $user = Auth::user();
        $user->tokens()->delete();
        $token = $user->createToken('auth')->plainTextToken;
        $user->load('business.subscription');

        return response()->json([
            'user' => $this->userResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('business.subscription');
        return response()->json(['user' => $this->userResource($user)]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user->name = $data['name'];
        $user->save();

        if ($user->business) {
            $user->business->fill([
                'name' => $data['business_name'] ?? $user->business->name,
                'address' => $data['business_address'] ?? $user->business->address,
                'phone' => $data['business_phone'] ?? $user->business->phone,
            ])->save();
        }

        $user->load('business.subscription');

        return response()->json(['user' => $this->userResource($user)]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => __('If that email is registered, we have sent a password reset link.'),
            ]);
        }

        return response()->json([
            'message' => __('If that email is registered, we have sent a password reset link.'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__('This password reset token is invalid or has expired.')],
            ]);
        }

        return response()->json(['message' => __('Your password has been reset.')]);
    }

    private function userResource($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'business_id' => $user->business_id,
            'business' => $user->relationLoaded('business') ? [
                'id' => $user->business->id,
                'name' => $user->business->name,
                'address' => $user->business->address,
                'phone' => $user->business->phone,
            ] : null,
            'subscription' => $user->relationLoaded('business') && $user->business->relationLoaded('subscription')
                ? [
                    'plan' => $user->business->subscription->plan,
                    'status' => $user->business->subscription->status,
                    'trial_ends_at' => $user->business->subscription->trial_ends_at?->toIso8601String(),
                    'current_period_end' => $user->business->subscription->current_period_end?->toIso8601String(),
                    'is_pro' => $user->business->isOnProPlan(),
                    'is_active' => $user->business->subscription->isActive(),
                ]
                : null,
        ];
    }
}
