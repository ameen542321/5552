<?php

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */

    'guards' => [

        // المستخدم العادي
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // الأدمن (إن كان لديك)
        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        // ⭐ المحاسب — Guard مستقل
        'accountant' => [
            'driver' => 'session',
            'provider' => 'accountants',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        // المستخدمين
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        // الأدمن (اختياري)
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],

        // ⭐ المحاسبين — Provider مستقل
        'accountants' => [
            'driver' => 'eloquent',
            'model' => App\Models\Accountant::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset Brokers
    |--------------------------------------------------------------------------
    */

    'passwords' => [

        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        // ⭐ إعادة تعيين كلمة مرور المحاسب
        'accountants' => [
            'provider' => 'accountants',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
