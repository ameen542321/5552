<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'price' => 100,
            'cost_price' => 60,
            'quantity' => 10,
            'status' => 'active',
            'product_type' => 'standard',
            'usage_type' => Product::USAGE_TYPE_SALE,
            'waste_percentage' => 0,
            'roll_length' => 0,
            'is_splittable' => false,
            'items_per_unit' => 1,
            'min_stock' => 1,
        ];
    }
}
