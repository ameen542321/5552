<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        $this->call(PlansSeeder::class);
        $this->call(UsersMigrationSeeder::class);
        $this->call(StoresSeeder::class);
        $this->call(AccountantsSeeder::class);

        // 10 مستخدمين مفعلين
        User::factory()->count(10)->create([
            'status' => 'active',
            'plan_id' => 1, // ← الخطة الأساسية
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        // 5 مستخدمين موقوفين
        User::factory()->count(5)->create([
            'status' => 'suspended',
            'plan_id' => 2, // ← خطة أخرى
            'expires_at' => Carbon::now()->addDays(15),
        ]);

        // 5 مستخدمين بخطط مختلفة
        User::factory()->count(5)->create(function () {
            return [
                'status' => 'active',
                'plan_id' => collect([1, 2, 3])->random(), // ← بدل basic/silver/gold
                'expires_at' => Carbon::now()->addDays(rand(5, 60)),
            ];
        });

        // 5 مستخدمين منتهية اشتراكاتهم
        User::factory()->count(5)->create([
            'status' => 'suspended',
            'plan_id' => 1,
            'expires_at' => Carbon::now()->subDays(rand(1, 30)),
        ]);
    }
}
