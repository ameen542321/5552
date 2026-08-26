<?php

// إصلاح مطبق: تُجهز بيانات الخطط وسلة المحذوفات في Controller بدل تنفيذ قراءتها داخل Blade.

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Accountant;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;

class UserController
{
    /**
     * index -> عرض قائمة المستخدمين (مع استثناء الأدمن)
     */
    public function index(Request $request): View
    {
        $query = User::where('role', '!=', 'admin');

        // البحث
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        // فلترة الدور
        if ($request->role && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // فلترة الحالة
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('id', 'desc')->paginate(15);
        $plans = Plan::all();
        $trashCount = User::onlyTrashed()->count();

        return view('admin.users.index', compact('users', 'plans', 'trashCount'));
    }

    /**
     * store -> حفظ مستخدم جديد (المتجر يُنشأ في المودل)
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'plan_id' => 'nullable|exists:plans,id',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $plan = $request->plan_id ? Plan::find($request->plan_id) : null;

                User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($request->password),
                    'plan_id' => $request->plan_id,
                    'role' => User::ROLE_USER,
                    'status' => User::STATUS_ACTIVE,
                    'allowed_stores' => $plan ? $plan->allowed_stores : 1,
                    'allowed_accountants' => $plan ? $plan->allowed_accountants : 1,
                    'welcome_shown' => 0,
                ]);
            });

            return redirect()->route('admin.users.index')->with('success', 'تم إضافة التاجر بنجاح.');
        } catch (\Exception $e) {
            return back()->with('error', 'فشل الإنشاء: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * show -> عرض تفاصيل المستخدم
     */
    public function show(User $user): View
    {
        if ($user->role === 'admin') abort(403);

        $accountants = Accountant::where('user_id', $user->id)->get();
        $stores = $user->stores;

        return view('admin.users.show', compact('user', 'stores', 'accountants'));
    }

    /**
     * edit -> واجهة التعديل
     */
    public function edit(User $user): View
    {
        if ($user->role === 'admin') abort(403);
        $plans = Plan::all();
        return view('admin.users.edit', compact('user', 'plans'));
    }

    /**
     * update -> تحديث البيانات (استعادة الحقول المفقودة)
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->role === User::ROLE_ADMIN) {
            return back()->with('error', 'لا يمكن تعديل بيانات المدير العام');
        }

        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'email'               => 'required|email|unique:users,email,' . $user->id,
            'phone'               => 'nullable|string|max:20|unique:users,phone,' . $user->id,
            'status'              => 'required|in:active,suspended',
            'plan_id'             => 'nullable|exists:plans,id',
            'subscription_end_at' => 'nullable|date', // عادت
            'expires_at'          => 'nullable|date', // عادت
            'allowed_stores'      => 'required|integer|min:1',
            'allowed_accountants' => 'required|integer|min:0',
        ]);

        $user->update($data);

        if ($user->wasChanged('status') && $user->status === 'suspended') {
            $user->stores()->update(['status' => 'suspended']);
        }

        return redirect()->route('admin.users.index')->with('success', 'تم تحديث البيانات بنجاح');
    }

    /**
     * toggleStatus -> تبديل الحالة
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->role === 'admin') {
            return back()->with('error', 'لا يمكن تعديل حالة المدير العام');
        }

        $user->status = ($user->status === 'active') ? 'suspended' : 'active';
        $user->save();

        return back()->with('success', 'تم تحديث الحالة بنجاح');
    }

    /**
     * trash -> عرض المحذوفين ناعماً
     */
    public function trash(): View
    {
        $users = User::onlyTrashed()->latest()->paginate(10);
        return view('admin.users.trash', compact('users'));
    }

    /**
     * restore -> استعادة
     */
    public function restore($id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return back()->with('success', 'تم استعادة الحساب بنجاح');
    }

    /**
     * destroy -> الحذف النهائي مع Cascade
     */
    public function forceDelete($id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        if ($user->role === 'admin') {
            return back()->with('error', 'لا يمكن حذف حساب الإدارة');
        }

        try {
            // تفعيل Cascade في قاعدة البيانات
            $user->forceDelete();
            return redirect()->route('admin.users.index')->with('success', 'تم الحذف النهائي بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }
    public function destroy(User $user)
{
    // هذا السجل لن يختفي من القاعدة، بل سيتم تعبئة deleted_at فقط
    $user->delete();

    // بما أن الكاسكيد لا يعمل في الحذف الناعم، يجب إخفاء المتاجر يدوياً (اختياري)
    $user->stores()->update(['status' => 'suspended']);

    return back()->with('success', 'تم نقل التاجر إلى سلة المهملات.');
}
}
