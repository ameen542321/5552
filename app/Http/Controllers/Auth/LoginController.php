<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter; // لإدارة محاولات الدخول
use App\Models\User; // أو موديول المحاسب حسب الحاجة
use Illuminate\Support\Str;
use App\Services\SupportSessionService;
use App\Services\SecurityEventService;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        // مفتاح مركب يمنع التحايل على الحد، دون تمكين مهاجم من إيقاف حساب الضحية.
        $throttleKey = hash('sha256', Str::lower($request->input('email')).'|'.$request->ip());

        // فحص الحالة قبل كل شيء
        $user = \App\Models\User::where('email', $request->email)->first()
                ?? \App\Models\Accountant::where('email', $request->email)->first();

        if ($user && $user->status === 'suspended') {
            return back()->withErrors(['email' => 'حسابك موقوف، راجع مالك المتجر.']);
        }

        // فحص عدد المحاولات
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            app(SecurityEventService::class)->record(
                'AUTH.RATE_LIMITED', 'authentication', 'high',
                'سيدي، قبضنا على محاولة دخول متكررة وتم تقييد المصدر مؤقتًا.',
                ['confidence' => 90, 'subject' => Str::lower($request->input('email')), 'evidence' => ['email_hash' => hash('sha256', Str::lower($request->input('email'))), 'attempts' => RateLimiter::attempts($throttleKey)]]
            );
            return back()->withErrors([
                'email' => 'تم تجاوز عدد المحاولات المسموح. حاول مرة أخرى لاحقًا.'
            ]);
        }

        // محاولات الدخول
        if (Auth::guard('accountant')->attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);
            return $this->handleLoginSuccess($request, 'accountant');
        }

        if (Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);
            return $this->handleLoginSuccess($request, 'web');
        }

        // تسجيل فشل المحاولة
        RateLimiter::hit($throttleKey, 3600);
        app(SecurityEventService::class)->record(
            'AUTH.LOGIN_FAILED', 'authentication', RateLimiter::attempts($throttleKey) >= 3 ? 'medium' : 'low',
            'سيدي، رصدنا محاولة دخول غير ناجحة.',
            ['confidence' => 100, 'subject' => Str::lower($request->input('email')), 'evidence' => ['email_hash' => hash('sha256', Str::lower($request->input('email'))), 'attempts' => RateLimiter::attempts($throttleKey)]]
        );

        return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة'])->onlyInput('email');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    // public function login(Request $request)
    // {
    //     $credentials = $request->validate([
    //         'email'    => ['required', 'email'],
    //         'password' => ['required'],
    //     ]);

    //     $remember = $request->boolean('remember');

    //     // 1) محاولة دخول المحاسب
    //     if (Auth::guard('accountant')->attempt($credentials, $remember)) {
    //         return $this->handleLoginSuccess($request, 'accountant');
    //     }

    //     // 2) محاولة دخول المستخدم (مالك أو أدمن)
    //     if (Auth::guard('web')->attempt($credentials, $remember)) {
    //         return $this->handleLoginSuccess($request, 'web');
    //     }

    //     return back()
    //         ->withErrors(['email' => 'بيانات الدخول غير صحيحة'])
    //         ->onlyInput('email');
    // }

    /**
     * دالة موحدة للتعامل مع نجاح الدخول وتوجيه كل رتبة لمكانها
     */
    protected function handleLoginSuccess(Request $request, $guard)
    {
        // إغلاق الجلسات الأخرى لضمان عدم التداخل (Security Best Practice)
        if ($guard === 'accountant') {
            Auth::guard('web')->logout();
        } else {
            Auth::guard('accountant')->logout();
        }

        $request->session()->regenerate();

        $user = Auth::guard($guard)->user();

        if ($user?->must_reset_password) {
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('password.request')->with('warning', 'سيدي، يلزم إعادة تعيين كلمة المرور قبل الدخول مجددًا.');
        }

        if ($guard === 'web' && $user?->role === 'admin') {
            app(SecurityEventService::class)->record(
                'AUTH.ADMIN_LOGIN', 'authentication', 'info',
                'سيدي، تم تسجيل دخول إداري بنجاح.',
                ['confidence' => 100, 'actor' => $user, 'subject' => (string) $user->id]
            );
        }

        // التوجيه الذكي بناءً على الرتبة والحارس
        if ($guard === 'accountant') {
            return redirect()->route('accountant.dashboard');
        }

        return ($user->role === 'admin')
            ? redirect()->route('admin.dashboard.index')
            : redirect()->route('user.dashboard');
    }

    /**
     * تسجيل الخروج الشامل (Universal Logout)
     */
    public function logout(Request $request)
    {
        $supportSessions = app(SupportSessionService::class);
        if ($supportSessions->active($request)) {
            $supportSessions->stop($request);
        }

        // تسجيل الخروج من كافة الحراس المتاحة
        Auth::guard('web')->logout();
        Auth::guard('accountant')->logout();
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'تم تسجيل الخروج بنجاح.');
    }
}
