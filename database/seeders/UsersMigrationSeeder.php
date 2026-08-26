<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Plan;
use Carbon\Carbon;

class UsersMigrationSeeder extends Seeder
{
    public function run(): void
    {
        // جلب الخطة الأساسية
        $basicPlan = Plan::where('name', 'basic')->first();

        if (!$basicPlan) {
            throw new \Exception("❌ لم يتم العثور على الخطة الأساسية. تأكد من تشغيل PlansSeeder أولاً.");
        }

        // تحديث جميع المستخدمين
        foreach (User::all() as $user) {

            $user->update([
                'status' => 'active',
                'suspension_reason' => null,
                'plan_id' => $basicPlan->id,
                'allowed_stores' => $basicPlan->allowed_stores,
                'allowed_accountants' => $basicPlan->allowed_accountants,
                'subscription_end_at' => Carbon::now()->addMonths(6),
            ]);
        }
    }
}
