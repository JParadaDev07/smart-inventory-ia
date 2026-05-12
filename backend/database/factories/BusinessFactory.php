<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'owner_user_id' => null,
            'name' => $this->faker->company,
            'address' => $this->faker->optional()->streetAddress,
            'phone' => $this->faker->optional()->phoneNumber,
            'whatsapp_number' => $this->faker->optional()->phoneNumber,
            'whatsapp_enabled' => false,
        ];
    }
}

