<?php

namespace App\Services\Auth;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterService
{
    public function __construct(
        private int $trialDays = 14
    ) {
        $this->trialDays = (int) config('services.subscription.trial_days', 14);
    }

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $business = Business::create([
                'name' => $data['business_name'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            $user = User::create([
                'business_id' => $business->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $business->update(['owner_user_id' => $user->id]);

            $user->sendEmailVerificationNotification();

            Subscription::create([
                'business_id' => $business->id,
                'plan' => 'basic',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays($this->trialDays),
            ]);

            return $user;
        });
    }
}
