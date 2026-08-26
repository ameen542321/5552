<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
   
public function definition()
{
    $name = fake()->name();

    return [
        'name' => $name,
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => bcrypt('password'),
        'remember_token' => Str::random(10),

        // Slug
        'slug' => Str::slug($name) . '-' . Str::random(6),

        // لا نفترض وجود خطة بالمعرّف 1 في قاعدة الاختبار الجديدة.
        // الاختبارات التي تحتاج خطة محددة تنشئها وتمرر معرّفها صراحةً.
        'plan_id' => null,
        // حالة
        'status' => 'active',

        // تاريخ الاشتراك
        'created_at' => now()->subDays(rand(1, 365)),

        // تاريخ الانتهاء
        'expires_at' => now()->addDays(rand(1, 60)),
    ];
}

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
