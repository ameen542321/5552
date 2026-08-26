<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    /**
     * عرض صفحة "نسيت كلمة المرور"
     */
    public function showLinkRequestForm()
    {
        return view('auth.forgot');
    }

    /**
     * استقبال البريد وإرسال رابط إعادة التعيين
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'لا يوجد مستخدم مسجل بهذا البريد.',
            ]);
        }

        // إنشاء توكن وحفظه في جدول password_resets
        $token = Str::random(64);

        DB::table('password_resets')->updateOrInsert(
            ['email' => $user->email],
            [
                'token'      => bcrypt($token),
                'created_at' => Carbon::now(),
            ]
        );

        // إرسال الإشعار
        $user->notify(new ResetPasswordNotification($token, $user->email));

        return back()->with('status', 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.');
    }
}
