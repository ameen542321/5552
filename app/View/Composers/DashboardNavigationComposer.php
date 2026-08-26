<?php

namespace App\View\Composers;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardNavigationComposer
{
    public function compose(View $view): void
    {
        switch ($view->name()) {
            case 'dashboard.navbars.user':
                $this->composeOwnerNavigation($view);
                break;
            case 'dashboard.navbars.admin':
                $this->composeAdminNavigation($view);
                break;
            case 'dashboard.navbars.accountant':
                $this->composeAccountantNavigation($view);
                break;
        }
    }

    private function composeOwnerNavigation(View $view): void
    {
        $sharedViewData = $view->getData();
        $authenticatedOwner = $sharedViewData['global_auth'] ?? Auth::guard('web')->user();
        $subscriptionPlan = $sharedViewData['global_plan'] ?? $authenticatedOwner?->plan;

        $currentStoreCount = $authenticatedOwner->stores()->withTrashed()->count();
        $currentAccountantCount = $authenticatedOwner->accountants()->count();
        $allowedStoreCount = $subscriptionPlan->allowed_stores ?? 0;

        $latestNotifications = Notification::forUser($authenticatedOwner->id)->latest()->take(5)->get();
        $unreadNotificationCount = Notification::unreadCountFor($authenticatedOwner->id);
        $activeStores = $authenticatedOwner->stores()->where('status', 'active')->orderBy('name')->get();

        $currentStore = null;
        $currentStoreId = null;
        $currentStoreName = null;
        $isInsideStore = false;
        $storeSwitchRouteName = 'user.stores.show';

        if (request()->route('store')) {
            $storeRouteParameter = request()->route('store');
            $currentStoreId = is_object($storeRouteParameter)
                ? $storeRouteParameter->id
                : (int) $storeRouteParameter;
            $currentStore = is_object($storeRouteParameter)
                ? $storeRouteParameter
                : $authenticatedOwner->stores()->where('id', $currentStoreId)->first();
            $currentStoreName = $currentStore?->name ?? 'المتجر';
            $isInsideStore = true;
        } elseif (request()->routeIs('user.employees.index') && request('store')) {
            $currentStore = $authenticatedOwner->stores()->where('id', (int) request('store'))->first();
            if ($currentStore) {
                $currentStoreId = (int) $currentStore->id;
                $currentStoreName = $currentStore->name;
                $isInsideStore = true;
            }
        }

        if ($isInsideStore && $currentStoreId) {
            $storeSwitchRouteName = $this->resolveStoreSwitchRouteName();
        }

        $storeSwitchRouteForCurrentPage = request()->routeIs('user.stores.reports.*')
            ? 'user.stores.reports.index'
            : $storeSwitchRouteName;
        $storeSwitchUrls = $activeStores->mapWithKeys(function ($activeStore) use ($storeSwitchRouteForCurrentPage) {
            $storeSwitchParameters = $storeSwitchRouteForCurrentPage === 'user.employees.index'
                ? ['store' => $activeStore->id]
                : $activeStore->id;

            return [(string) $activeStore->id => route($storeSwitchRouteForCurrentPage, $storeSwitchParameters)];
        });

        $view->with([
            'auth' => $authenticatedOwner,
            'plan' => $subscriptionPlan,
            'currentStores' => $currentStoreCount,
            'currentAccountants' => $currentAccountantCount,
            'allowedStores' => $allowedStoreCount,
            'latestNotifications' => $latestNotifications,
            'unreadCount' => $unreadNotificationCount,
            'activeStores' => $activeStores,
            'storeId' => $currentStoreId,
            'storeName' => $currentStoreName,
            'isInStore' => $isInsideStore,
            'storeSwitchUrls' => $storeSwitchUrls,
            'storeMenuItems' => $currentStoreId ? $this->ownerStoreMenuItems($currentStoreId) : [],
        ]);
    }

    private function resolveStoreSwitchRouteName(): string
    {
        return match (true) {
            request()->routeIs('user.stores.daily') => 'user.stores.daily',
            request()->routeIs('user.stores.products.*') => 'user.stores.products.index',
            request()->routeIs('user.stores.purchase-orders.*') => 'user.stores.purchase-orders.index',
            request()->routeIs('user.stores.transfers.*') => 'user.stores.transfers.index',
            request()->routeIs('user.stores.internal-use.*') => 'user.stores.internal-use.report.view',
            request()->routeIs('user.stores.expenses.*') => 'user.stores.expenses.index',
            request()->routeIs('user.stores.employees.*'), request()->routeIs('user.employees.index') => 'user.employees.index',
            request()->routeIs('user.stores.invoices.*') => 'user.stores.invoices.index',
            default => 'user.stores.show',
        };
    }

    private function ownerStoreMenuItems(int $currentStoreId): array
    {
        return [
            ['url' => route('user.stores.show', ['store' => $currentStoreId]), 'icon' => 'gauge', 'label' => 'لوحة المتجر', 'active' => request()->routeIs('user.stores.show')],
            ['url' => route('user.stores.daily', ['store' => $currentStoreId]), 'icon' => 'chart-line', 'label' => 'مبيعات اليوم', 'active' => request()->routeIs('user.stores.daily')],
            ['url' => route('user.stores.invoices.index', ['store' => $currentStoreId]), 'icon' => 'file-invoice', 'label' => 'الفواتير', 'active' => request()->routeIs('user.stores.invoices.*')],
            ['url' => route('user.stores.products.index', ['store' => $currentStoreId]), 'icon' => 'boxes', 'label' => 'المنتجات', 'active' => request()->routeIs('user.stores.products.*')],
            ['url' => route('user.stores.transfers.index', ['store' => $currentStoreId]), 'icon' => 'right-left', 'label' => 'النقل المخزني', 'active' => request()->routeIs('user.stores.transfers.*')],
            ['url' => route('user.stores.purchase-orders.index', ['store' => $currentStoreId]), 'icon' => 'clipboard-list', 'label' => 'طلبيات توريد', 'active' => request()->routeIs('user.stores.purchase-orders.*')],
            ['url' => route('user.stores.internal-use.report.view', ['store' => $currentStoreId]), 'icon' => 'chart-line', 'label' => 'المشتريات', 'active' => request()->routeIs('user.stores.internal-use.*')],
            ['url' => route('user.stores.expenses.index', ['store' => $currentStoreId]), 'icon' => 'receipt', 'label' => 'المصروف', 'active' => request()->routeIs('user.stores.expenses.*')],
            [
                'url' => route('user.employees.index', ['store' => $currentStoreId]),
                'icon' => 'users',
                'label' => 'الموظفين',
                'active' => request()->routeIs('user.employees.index')
                    && (int) request('store') === (int) $currentStoreId,
            ],
        ];
    }

    private function composeAdminNavigation(View $view): void
    {
        $authenticatedAdmin = Auth::guard('web')->user();
        $latestNotifications = Notification::orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->filter(function (Notification $notification) use ($authenticatedAdmin): bool {
                return $notification->target_type === 'all'
                    || in_array($authenticatedAdmin->id, $notification->target_ids ?? []);
            });

        $view->with([
            'auth' => $authenticatedAdmin,
            'unreadCount' => Notification::unreadCountFor($authenticatedAdmin->id),
            'latestNotifications' => $latestNotifications,
        ]);
    }

    private function composeAccountantNavigation(View $view): void
    {
        $authenticatedAccountant = Auth::guard('accountant')->user();

        $view->with([
            'auth' => $authenticatedAccountant,
            'latestNotifications' => $authenticatedAccountant->notificationsForAccountant()->take(5)->get(),
            'unreadCount' => $authenticatedAccountant->unreadNotificationsCountForAccountant(),
        ]);
    }
}
