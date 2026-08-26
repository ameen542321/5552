<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = $this->faker->company;

        return [
            'user_id' => User::factory(), // قاعدة: لا متجر بدون مستخدم
            'name' => $name,
            'description' => $this->faker->paragraph,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'logo' => null,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'status' => 'active',
            'suspension_reason' => null,
        ];
    }
}
