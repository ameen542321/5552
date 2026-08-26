<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Str;

class StoresSeeder extends Seeder
{
    public function run(): void
    {
        // نجلب جميع المستخدمين الذين دورهم user
        $users = User::where('role', 'user')->get();

        foreach ($users as $user) {

            // عدد المتاجر المسموح بها حسب الخطة
            $count = $user->allowed_stores ?? 0;

            for ($i = 1; $i <= $count; $i++) {

                $name = "متجر {$user->name} رقم {$i}";

                Store::create([
                    'user_id'     => $user->id,
                    'name'        => $name,
                    'description' => "وصف متجر {$user->name} رقم {$i}",
                    'phone'       => '05' . rand(10000000, 99999999),
                    'address'     => "عنوان متجر {$user->name} رقم {$i}",
                    'logo'        => null, // يمكن لاحقًا إضافة رفع صور
                    'slug'        => Str::slug($name) . '-' . Str::random(5),
                    'status'      => 'active',
                    'suspension_reason' => null,
                ]);
            }
        }
    }
}
