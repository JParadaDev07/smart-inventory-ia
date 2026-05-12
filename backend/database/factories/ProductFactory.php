<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'business_id' => 1,
            'name' => $this->faker->word(),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'cost_price' => $this->faker->randomFloat(2, 0, 5000),
            'sale_price' => $this->faker->randomFloat(2, 0, 8000),
            'current_stock' => $this->faker->numberBetween(0, 50),
            'minimum_stock' => $this->faker->numberBetween(0, 10),
            'supplier_lead_time_days' => $this->faker->numberBetween(0, 30),
            'perecedero' => false,
            'expired_mode' => 'bloquear',
            'expiration_alert_days' => 7,
        ];
    }
}

