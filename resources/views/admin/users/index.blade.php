{{-- إصلاح مطبق: القائمة والمودالات تستخدم البيانات المحضرة والتأكيدات والمكونات المركزية المتجاوبة. --}}
@extends('dashboard.app')
@section('content')

<div class="mx-auto max-w-7xl p-4 sm:p-6"
     x-data="{
        openAddModal: {{ $errors->any() || request()->boolean('add') ? 'true' : 'false' }},
        openEditModal: false,
        editUser: {}
     }">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="ui-title text-2xl font-semibold">إدارة المستخدمين</h1>
            <p class="ui-text-soft mt-1">عرض وإدارة حسابات التجار والمحاسبين</p>
        </div>

        <button @click="openAddModal = true"
            class="ui-btn ui-btn-primary w-full sm:w-auto">
            <i class="fa-solid fa-user-plus"></i>
            إضافة مستخدم
        </button>
    </div>

    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" dir="rtl">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="ابحث بالاسم أو البريد..."
               aria-label="البحث بالاسم أو البريد"
               class="ui-input">

        <select name="status" aria-label="فلترة المستخدمين حسب الحالة" class="ui-input">
            <option value="all">كل الحالات</option>
            <option value="active" {{ request('status')=='active' ? 'selected' : '' }}>نشط</option>
            <option value="suspended" {{ request('status')=='suspended' ? 'selected' : '' }}>موقوف</option>
        </select>

        <button type="submit" class="ui-btn ui-btn-secondary w-full">
            تطبيق الفلترة
        </button>
    </form>

    <div class="ui-table-wrap">
        <table class="ui-table w-full text-right">
            <thead class="ui-surface-muted-bg  ui-text-muted ">
                <tr>
                    <th class="p-4 w-16 text-center">#</th>
                    <th class="p-4 text-right">المستخدم</th>
                    <th class="p-4 hidden md:table-cell text-center">الدور</th>
                    <th class="p-4 hidden md:table-cell text-center">الحالة</th>
                    <th class="p-4 hidden md:table-cell text-center">انتهاء الاشتراك</th>
                    <th class="p-4 text-center">التحكم</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-ui-border dark:divide-ui-border">
                @foreach($users as $user)
                    @php
                        $daysLeft = $user->subscription_end_at ? \Carbon\Carbon::now()->diffInDays($user->subscription_end_at, false) : null;
                        $subColor = 'ui-text-muted';
                        if ($daysLeft !== null) {
                            $subColor = ($daysLeft < 0) ? 'ui-status-danger' : (($daysLeft <= 7) ? 'ui-status-warning' : 'ui-status-success');
                        }
                    @endphp

                    <tr class="ui-surface-muted-bg  transition">
                        <td class="p-4 ui-text-muted text-center">{{ $user->id }}</td>
                        <td class="p-4 text-right">
                            <div class="font-medium ui-text-muted ui-title">{{ $user->name }}</div>
                            <div class="ui-text-caption ui-text-muted">{{ $user->email }}</div>
                        </td>
                        <td class="p-4 hidden md:table-cell text-center">
                            <span class="px-2 py-1 {{ $user->role === 'accountant' ? 'ui-status-info-bg  ui-status-info ' : 'ui-status-warning-bg  ui-status-warning ' }} rounded-md ui-text-caption">
                                {{ $user->role === 'accountant' ? 'محاسب' : 'تاجر' }}
                            </span>
                        </td>
                        <td class="p-4 hidden md:table-cell text-center">
                            <form action="{{ route('admin.users.toggleStatus', $user->id) }}" method="POST"
                                  data-ui-confirm="سيتم {{ $user->status === 'active' ? 'إيقاف' : 'تفعيل' }} حساب {{ $user->name }}."
                                  data-ui-confirm-title="تأكيد تغيير حالة الحساب">
                                @csrf @method('PATCH')
                                <button type="submit" class="flex items-center justify-center gap-1.5 mx-auto hover:opacity-75 transition" aria-label="{{ $user->status === 'active' ? 'إيقاف' : 'تفعيل' }} حساب {{ $user->name }}">
                                    <div class="w-2 h-2 rounded-full {{ $user->status === 'active' ? 'ui-status-success-bg shadow-[0_0_5px_rgba(16,185,129,0.5)]' : 'ui-status-danger-bg shadow-[0_0_5px_rgba(239,68,68,0.5)]' }}"></div>
                                    <span class="{{ $user->status === 'active' ? 'ui-status-success' : 'ui-status-danger' }} ui-text-caption font-bold">
                                        {{ $user->status === 'active' ? 'نشط' : 'موقوف' }}
                                    </span>
                                </button>
                            </form>
                        </td>
                        <td class="p-4 hidden md:table-cell text-center {{ $subColor }} font-medium ui-text-caption">
                            {{ $user->subscription_end_at ? \Carbon\Carbon::parse($user->subscription_end_at)->format('Y-m-d') : '—' }}
                        </td>
                        <td class="p-4">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('admin.users.show', $user->id) }}" class="p-2 ui-status-info transition" title="عرض" aria-label="عرض المستخدم {{ $user->name }}"><i class="fa-solid fa-eye"></i></a>
                                <button @click='openEditModal = true; editUser = @js([
                                            "id" => $user->id,
                                            "name" => $user->name,
                                            "phone" => $user->phone ?? "",
                                            "email" => $user->email,
                                            "status" => $user->status,
                                            "plan_id" => $user->plan_id,
                                            "allowed_stores" => $user->allowed_stores,
                                            "allowed_accountants" => $user->allowed_accountants,
                                            "subscription_end_at" => $user->subscription_end_at ? \Carbon\Carbon::parse($user->subscription_end_at)->format("Y-m-d") : "",
                                            "expires_at" => $user->expires_at ? \Carbon\Carbon::parse($user->expires_at)->format("Y-m-d") : "",
                                        ])'
                                    class="p-2 ui-status-warning transition" title="تعديل" aria-label="تعديل المستخدم {{ $user->name }}"><i class="fa-solid fa-pen-to-square"></i></button>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST"
                                      data-ui-confirm="سيُنقل حساب {{ $user->name }} إلى سلة المحذوفات."
                                      data-ui-confirm-title="نقل المستخدم إلى السلة؟">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-2 ui-status-danger transition" title="نقل إلى السلة" aria-label="نقل المستخدم {{ $user->name }} إلى سلة المحذوفات"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>

    <div class="mt-6">
        <a href="{{ route('admin.users.trash') }}" class="inline-flex items-center gap-2 px-4 py-2 ui-surface-muted-bg border ui-border ui-text-muted rounded-xl text-sm relative ui-status-danger-bg transition-all group">
            <i class="fa-solid fa-trash-arrow-up ui-text-caption group-hover:animate-bounce"></i> سلة المحذوفات
            @if($trashCount > 0)
                <span class="absolute -top-1 -left-1 w-4 h-4 ui-status-danger-bg ui-title ui-text-caption flex items-center justify-center rounded-full border ui-border font-bold">{{ $trashCount }}</span>
            @endif
        </a>
    </div>

    <div x-show="openAddModal" x-cloak class="ui-modal-backdrop p-4" role="dialog" aria-modal="true" aria-labelledby="add-user-title">
        <div @click.away="openAddModal = false" class="ui-modal-panel relative max-h-[90vh] w-full max-w-md overflow-y-auto p-5 sm:p-6">
            <button type="button" @click="openAddModal = false" class="ui-modal-close-danger" aria-label="إغلاق نافذة إضافة المستخدم">×</button>
            <h3 id="add-user-title" class="ui-title mb-5 flex items-center justify-center gap-2 text-center text-lg font-bold" dir="rtl">
                <i class="fa-solid fa-user-plus ui-status-info"></i> إضافة تاجر جديد
            </h3>

            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4 text-right" dir="rtl">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block ui-text-caption ui-text-muted mb-1">الاسم</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="ui-input w-full">
                    </div>
                    <div>
                        <label class="block ui-text-caption ui-text-muted mb-1">الهاتف</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="ui-input w-full">
                    </div>
                </div>
                <div>
                    <label class="block ui-text-caption ui-text-muted mb-1">البريد الإلكتروني</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="ui-input w-full">
                    @error('email')
                        <p class="ui-text-caption ui-status-danger mt-1 font-bold">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block ui-text-caption ui-text-muted mb-1">الخطة</label>
                    <select name="plan_id" class="ui-input w-full cursor-pointer">
                        <option value="">خطة تلقائية</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <input type="password" name="password" placeholder="كلمة المرور" aria-label="كلمة المرور" required class="ui-input w-full">
                        @error('password')
                            <p class="ui-text-caption ui-status-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <input type="password" name="password_confirmation" placeholder="تأكيد الكلمة" aria-label="تأكيد كلمة المرور" required class="ui-input w-full">
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-2 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" @click="openAddModal = false" class="ui-btn ui-btn-danger">إلغاء</button>
                    <button type="submit" class="ui-btn ui-btn-primary">حفظ البيانات</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="openEditModal" x-cloak class="ui-modal-backdrop p-4" role="dialog" aria-modal="true" aria-labelledby="edit-user-title">
        <div @click.away="openEditModal = false" class="ui-modal-panel relative max-h-[90vh] w-full max-w-md overflow-y-auto p-5 sm:p-6">
            <button type="button" @click="openEditModal = false" class="ui-modal-close-danger" aria-label="إغلاق نافذة تعديل المستخدم">×</button>
            <h3 id="edit-user-title" class="ui-title mb-5 text-center text-lg font-bold" dir="rtl">تعديل بيانات التاجر</h3>
            <form method="POST" :action="`{{ url('admin/users') }}/${editUser.id}`" class="space-y-4 text-right" dir="rtl">
                @csrf @method('PUT')
                <div>
                    <label class="block ui-text-caption ui-text-muted mb-1">الاسم</label>
                    <input type="text" name="name" x-model="editUser.name" class="ui-input w-full">
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block ui-text-caption ui-text-muted mb-1">البريد</label>
                        <input type="email" name="email" x-model="editUser.email" class="ui-input w-full">
                    </div>
                    <div>
                        <label class="block ui-text-caption ui-text-muted mb-1">الهاتف</label>
                        <input type="text" name="phone" x-model="editUser.phone" class="ui-input w-full">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block ui-text-caption ui-text-muted mb-1">الحالة</label>
                        <select name="status" x-model="editUser.status" class="ui-input w-full">
                            <option value="active">نشط</option>
                            <option value="suspended">موقوف</option>
                        </select>
                    </div>
                    <div>
                        <label class="block ui-text-caption ui-text-muted mb-1">الخطة</label>
                        <select name="plan_id" x-model="editUser.plan_id" class="ui-input w-full">
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block ui-text-caption ui-text-muted">نهاية الاشتراك</label>
                        <input type="date" name="subscription_end_at" x-model="editUser.subscription_end_at" class="ui-input w-full">
                    </div>
                    <div>
                        <label class="block ui-text-caption ui-text-muted">إغلاق الحساب</label>
                        <input type="date" name="expires_at" x-model="editUser.expires_at" class="ui-input w-full">
                    </div>
                </div>
                <input type="hidden" name="allowed_stores" x-model="editUser.allowed_stores">
                <input type="hidden" name="allowed_accountants" x-model="editUser.allowed_accountants">
                <div class="flex flex-col-reverse gap-2 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" @click="openEditModal = false" class="ui-btn ui-btn-danger">إلغاء</button>
                    <button type="submit" class="ui-btn ui-btn-primary">تحديث</button>
                </div>
            </form>
        </div>
    </div>
</div>



@endsection
