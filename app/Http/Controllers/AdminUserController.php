<?php

namespace App\Http\Controllers;

use App\Models\User;

use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function suspend(User $user)
{
    // إيقاف المستخدم
    $user->update([
        'status' => 'expired'
    ]);

    // إيقاف جميع المتاجر التابعة له
    $user->stores()->update([
        'status' => 'suspended'
    ]);

    // إيقاف جميع المحاسبين التابعين له
    $user->accountants()->update([
        'status' => 'suspended'
    ]);

    return back()->with('success', 'تم إيقاف المستخدم وجميع المتاجر والمحاسبين');
}


public function activate(User $user)
{
    // تفعيل المستخدم
    $user->update([
        'status' => 'active'
    ]);

    // تفعيل المتاجر
    $user->stores()->update([
        'status' => 'active'
    ]);

    // تفعيل المحاسبين
    $user->accountants()->update([
        'status' => 'active'
    ]);

    return back()->with('success', 'تم تفعيل المستخدم وجميع المتاجر والمحاسبين');
}


}
