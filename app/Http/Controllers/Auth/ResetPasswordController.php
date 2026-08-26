<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    /**
     * عرض صفحة إعادة تعيين كلمة المرور
     */
    public function showResetForm(Request $request, string $token)
    {
        $email = $request->query('email');

        return view('auth.reset', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * تنفيذ إعادة تعيين كلمة المرور
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        DB::transaction(function () use ($request): void {
            // يمنع القفل استخدام الرابط نفسه بالتزامن في طلبين قبل حذف سجل الاستعادة.
            $record = DB::table('password_resets')
                ->where('email', $request->email)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                throw ValidationException::withMessages([
                    'email' => 'رابط إعادة التعيين غير صالح أو منتهي.',
                ]);
            }

            $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);
            if (Carbon::parse($record->created_at)->addMinutes($expiresInMinutes)->isPast()) {
                throw ValidationException::withMessages([
                    'email' => 'انتهت صلاحية رابط إعادة التعيين.',
                ]);
            }

            // الرمز الخام لا يخزن في القاعدة؛ لذلك يجب مقارنته بالنسخة المشفرة قبل السماح بتغيير كلمة المرور.
            if (! Hash::check((string) $request->token, $record->token)) {
                throw ValidationException::withMessages([
                    'email' => 'رابط إعادة التعيين غير صالح أو منتهي.',
                ]);
            }

            $user = User::where('email', $request->email)
                ->lockForUpdate()
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'email' => 'رابط إعادة التعيين غير صالح أو منتهي.',
                ]);
            }

            $user->forceFill([
                'password' => Hash::make($request->password),
                // إبطال جلسات «تذكرني» القديمة جزء من تأمين الحساب بعد تغيير كلمة المرور.
                'remember_token' => null,
                'must_reset_password' => false,
            ])->save();

            // حذف الرمز داخل المعاملة يجعل الرابط صالحًا لاستخدام ناجح واحد فقط.
            DB::table('password_resets')->where('email', $request->email)->delete();
        });

        return redirect()->route('login')->with('status', 'تم تحديث كلمة المرور بنجاح، يمكنك تسجيل الدخول الآن.');
    }
}
