<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * صفحة انتهاء الاشتراك
     */
    public function expired(Request $request)
    {
        $user = $request->user();

        // لو المستخدم مدير، ما المفروض يوصل هنا
        if ($user && $user->role === 'admin') {
            return redirect()->route('user.dashboard');
        }

        // لو المستخدم active، رجّعه للداشبورد
        if ($user && $user->status === 'active') {
            return redirect()->route('user.dashboard');
        }

        $lastSubscription = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['expired', 'cancelled'])
            ->latest('end_at')
            ->first();

        return view('subscriptions.expired', [
            'user' => $user,
            'lastSubscription' => $lastSubscription,
        ]);
    }

    /**
     * صفحة اختيار الخطة وتجديد الاشتراك
     */
    public function renew(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $currentSubscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_at', '>=', Carbon::now())
            ->latest('end_at')
            ->first();

        $allSubscriptions = Subscription::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $plans = [
            'basic' => [
                'name'            => 'الخطة العادية',
                'price'           => 500,
                'type'            => 'basic',
                'months'          => 6,
                'features'        => ['متجر واحد', '2 محاسبين', 'دعم أساسي'],
                'popular'         => false,
                'icon'            => 'fas fa-store',
            ],
            'silver' => [
                'name'            => 'الخطة الفضية',
                'price'           => 1400,
                'type'            => 'silver',
                'months'          => 6,
                'features'        => ['3 متاجر', '8 محاسبين', 'دعم متميز', 'تقارير متقدمة'],
                'popular'         => true,
                'icon'            => 'fas fa-star',
            ],
            'gold' => [
                'name'            => 'الخطة الذهبية',
                'price'           => 2700,
                'type'            => 'gold',
                'months'          => 6,
                'features'        => ['6 متاجر', '15 محاسب', 'دعم VIP', 'API', 'جميع التقارير'],
                'popular'         => false,
                'icon'            => 'fas fa-crown',
            ],
        ];

        return view('subscriptions.renew', [
            'user' => $user,
            'currentSubscription' => $currentSubscription,
            'allSubscriptions' => $allSubscriptions,
            'plans' => $plans,
        ]);
    }

    /**
     * معالجة طلب التجديد
     */
    public function processRenew(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'plan' => 'required|in:basic,silver,gold',
        ]);

        $planKey = $data['plan'];

        $plans = [
            'basic' => [
                'name'    => 'الخطة العادية',
                'price'   => 500,
                'type'    => 'basic',
                'months'  => 6,
            ],
            'silver' => [
                'name'    => 'الخطة الفضية',
                'price'   => 1400,
                'type'    => 'silver',
                'months'  => 6,
            ],
            'gold' => [
                'name'    => 'الخطة الذهبية',
                'price'   => 2700,
                'type'    => 'gold',
                'months'  => 6,
            ],
        ];

        $plan = $plans[$planKey];

        // البحث عن اشتراك نشط
        $activeSubscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('end_at', '>=', Carbon::now())
            ->latest('end_at')
            ->first();

        $now = Carbon::now();

        // تحديد تاريخ البداية
        if ($activeSubscription) {
            $startDate = Carbon::parse($activeSubscription->end_at)->addDay();
            $activeSubscription->update(['status' => 'expired']);
        } else {
            $startDate = $now;
        }

        $endDate = $startDate->copy()->addMonths($plan['months']);

        try {
            DB::beginTransaction();

            // 1️⃣ إنشاء الاشتراك الجديد
            $subscription = Subscription::create([
                'user_id'    => $user->id,
                'type'       => $plan['type'],
                'price'      => $plan['price'],
                'start_at'   => $startDate,
                'end_at'     => $endDate,
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2️⃣ تحديث المستخدم - ✅ الآن نستخدم القيم الصحيحة
            $user->update([
                'subscription_end_at' => $endDate,
                'status' => 'active',        // ✅ active بدلاً من 'نشط'
                'plan' => $planKey,
            ]);

            DB::commit();

            Log::info('Subscription renewed successfully', [
                'user_id' => $user->id,
                'plan' => $planKey,
                'subscription_id' => $subscription->id,
                'end_date' => $endDate->toDateTimeString()
            ]);

            session()->flash('subscription_success', 'تم تجديد اشتراكك بنجاح على ' . $plan['name'] . ' لمدة 6 أشهر');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Subscription renewal failed', [
                'user_id' => $user->id,
                'plan' => $planKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'حدث خطأ في تجديد الاشتراك: ' . $e->getMessage());
        }

        return redirect()->route('user.dashboard');
    }

    /**
     * عرض سجل الاشتراكات
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $subscriptions = Subscription::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('subscriptions.history', [
            'subscriptions' => $subscriptions
        ]);
    }

    /**
     * إلغاء اشتراك
     */
    public function cancel($id)
    {
        $subscription = Subscription::findOrFail($id);

        if ($subscription->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return redirect()->back()->with('error', 'لا تملك صلاحية إلغاء هذا الاشتراك');
        }

        $subscription->update(['status' => 'cancelled']);
        $subscription->delete();

        return redirect()->back()->with('success', 'تم إلغاء الاشتراك بنجاح');
    }
}
