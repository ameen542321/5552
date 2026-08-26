<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use App\Models\Accountant;

class AccountantsSeeder extends Seeder
{
    public function run(): void
    {
        // نجلب جميع المستخدمين الذين دورهم user
        $users = User::where('role', 'user')->get();

        foreach ($users as $user) {

            // عدد المحاسبين حسب الخطة
            $count = $user->allowed_accountants;

            // نجلب متاجر المستخدم
            $stores = $user->stores;

            if ($stores->count() === 0) {
                continue;
            }

            for ($i = 1; $i <= $count; $i++) {

                // نربط كل محاسب بمتجر عشوائي من متاجر المستخدم
                $store = $stores->random();

                Accountant::create([
                    'user_id' => $user->id,
                    'store_id' => $store->id,
                    'name' => "محاسب {$user->name} رقم {$i}",
                    'email' => "acc{$user->id}_{$i}@example.com",
                    'status' => 'active',
                    'suspension_reason' => null,
                ]);
            }
        }
    }
}
