<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {

        // Web middleware group
        $middleware->group('web', [

            // الكوكيز
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            // احتواء المصادر المقيدة أمنيًا قبل تنفيذ طلبات التطبيق.
            \App\Http\Middleware\BlockSecurityThreats::class,

            // تفعيل الجلسة
            \Illuminate\Session\Middleware\StartSession::class,

            // منع تكرار نفس الطلب التعديلي خلال فترة قصيرة
            \App\Http\Middleware\PreventDuplicateRequest::class,

            // مشاركة الأخطاء
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,

            // حماية CSRF
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,

            // ربط الروتات (يجب أن يكون دائماً قبل حراس المتاجر)
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Aliases
        $middleware->alias([
            'is.admin'   => \App\Http\Middleware\IsAdmin::class,

            /* |--- 🛡️ الحراس الجدد (مدمجة ومنظمة) --- */


            // حارس المتجر الشامل (يدمج: store.access, store.active)
            'store.master' => \App\Http\Middleware\UnifiedStoreGuard::class,

            // /* |--- 🛑 الحراس القدامى (للمراجعة فقط - سيتم حذفهم لاحقاً) ---
            // 'is.user'              => \App\Http\Middleware\IsUser::class,
            'subscription.active'  => \App\Http\Middleware\CheckSubscriptionActive::class,
            'subscription.warning' => \App\Http\Middleware\SubscriptionWarning::class,
            'store.active'         => \App\Http\Middleware\CheckStoreStatus::class,
            'store.access'         => \App\Http\Middleware\CheckStoreAccess::class,
            'check.suspended'      => \App\Http\Middleware\CheckUserSuspended::class,
            // 'active.welcome'       => \App\Http\Middleware\RedirectActiveUser::class,
            // 'accountant.auth'      => \App\Http\Middleware\AccountantAuth::class,
            // |-------------------------------------------------------------------------- */

            'redirect.dashboard' => \App\Http\Middleware\RedirectIfAuthenticatedToDashboard::class,
            'no.access'          => \App\Http\Middleware\NoAccess::class,
            'plan.limit'         => \App\Http\Middleware\CheckPlanLimit::class,
            // الحراس النهائيين
            // حارس المالك
            'owner.unified' => \App\Http\Middleware\UnifiedOwnerGuard::class,
            // حارس المحاسب
            'accountant.unified' => \App\Http\Middleware\UnifiedAccountantGuard::class,

               'store.check' => \App\Http\Middleware\UnifiedStoreGuard::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $exception) {
            if (app()->runningInConsole()) {
                return;
            }

            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('security_events')) {
                    app(\App\Services\SecurityEventService::class)->record(
                        'OPS.UNHANDLED_EXCEPTION',
                        'operations',
                        'high',
                        'سيدي، رصدنا خطأً غير معالج في التطبيق.',
                        [
                            'confidence' => 100,
                            'subject' => get_class($exception),
                            'evidence' => [
                                'exception' => get_class($exception),
                                'file' => basename($exception->getFile()),
                                'line' => $exception->getLine(),
                            ],
                        ]
                    );
                }
            } catch (\Throwable) {
                // لا يسمح لفشل الرصد بإخفاء الاستثناء الأصلي أو إنشاء حلقة تقارير.
            }
        });
    })

    ->create();
