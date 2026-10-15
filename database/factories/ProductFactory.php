<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 10, 500);

        return [
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->randomElement(['Tab', 'Syp', 'Cap']),
            'generic_name' => fake()->word(),
            'barcode' => fake()->unique()->ean13(),
            'unit' => 'pcs',
            'purchase_price' => $cost,
            'sale_price' => round($cost * 1.2, 2),
            'reorder_level' => 10,
            'is_active' => true,
        ];
    }
}
