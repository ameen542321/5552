<?php

namespace App\Providers;

use App\Models\OneSignalSetting;
use App\View\Composers\DashboardNavigationComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($composedView) {
            $composedView->with('settings', OneSignalSetting::first());
        });

        View::composer([
            'dashboard.navbars.user',
            'dashboard.navbars.admin',
            'dashboard.navbars.accountant',
        ], DashboardNavigationComposer::class);
    }
}
