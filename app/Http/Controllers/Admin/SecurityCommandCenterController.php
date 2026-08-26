<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SecurityEventActionRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\SecurityEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SecurityCommandCenterController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SecurityEvent::class);

        $filters = $request->validate([
            'severity' => ['nullable', 'in:info,low,medium,high,critical'],
            'status' => ['nullable', 'in:new,investigating,contained,resolved,false_positive'],
            'category' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = SecurityEvent::query()->with('assignee')->latest('last_seen_at');
        foreach (['severity', 'status', 'category'] as $filter) {
            $query->when($filters[$filter] ?? null, fn ($builder, $value) => $builder->where($filter, $value));
        }
        $query->when($filters['search'] ?? null, function ($builder, $search) {
            $builder->where(fn ($nested) => $nested
                ->where('event_code', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('source_ip', 'like', "%{$search}%"));
        });

        $summary = [
            'open' => SecurityEvent::open()->count(),
            'critical' => SecurityEvent::open()->whereIn('severity', ['high', 'critical'])->count(),
            'contained_today' => SecurityEvent::whereDate('contained_at', today())->count(),
            'needs_order' => SecurityEvent::where('status', 'new')->count(),
            'operational' => SecurityEvent::open()->where('category', 'operations')->count(),
            'failed_logins_today' => SecurityEvent::where('event_code', 'AUTH.LOGIN_FAILED')->whereDate('last_seen_at', today())->sum('occurrences'),
        ];
        $performanceEvents = SecurityEvent::query()
            ->where('detected_at', '>=', now()->subDays(30))
            ->get(['detected_at', 'acknowledged_at', 'contained_at', 'verified_at']);
        $averageMinutes = function (string $timestamp) use ($performanceEvents): ?int {
            $durations = $performanceEvents
                ->filter(fn (SecurityEvent $event) => $event->{$timestamp})
                ->map(fn (SecurityEvent $event) => $event->detected_at->diffInMinutes($event->{$timestamp}));

            return $durations->isEmpty() ? null : (int) round($durations->average());
        };
        $performance = [
            'acknowledge' => $averageMinutes('acknowledged_at'),
            'contain' => $averageMinutes('contained_at'),
            'verify' => $averageMinutes('verified_at'),
        ];

        return view('admin.security.index', [
            'events' => $query->paginate(20)->withQueryString(),
            'summary' => $summary,
            'categories' => SecurityEvent::query()->distinct()->orderBy('category')->pluck('category'),
            'performance' => $performance,
            'monitoring' => [
                'last_health_check' => Cache::get('security:health:last_run'),
                'response_enabled' => (bool) config('security_command_center.response_enabled'),
                'automatic_response_enabled' => (bool) config('security_command_center.automatic_response_enabled'),
            ],
        ]);
    }

    public function show(SecurityEvent $securityEvent): View
    {
        Gate::authorize('view', $securityEvent);

        return view('admin.security.show', [
            'event' => $securityEvent->load(['activities.user', 'assignee', 'acknowledger', 'verifier']),
            'admins' => User::query()->where('role', User::ROLE_ADMIN)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function action(SecurityEventActionRequest $request, SecurityEvent $securityEvent, SecurityEventService $security): mixed
    {
        Gate::authorize('update', $securityEvent);

        $validated = $request->validated();
        $security->transition(
            $securityEvent,
            $request->user('web'),
            $validated['action'],
            $validated['note'] ?? null,
            $validated['assigned_to'] ?? null,
        );

        return back()->with('success', 'تم تنفيذ الأمر وتوثيقه في سجل الحادث.');
    }

    public function runCheck(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('viewAny', SecurityEvent::class);
        $automatic = $request->boolean('automatic');
        $lockSeconds = $automatic ? 900 : 60;

        if (! Cache::add('security:maintenance:health-lock', true, now()->addSeconds($lockSeconds))) {
            $message = 'سيدي، نُفذ الفحص مؤخرًا؛ لم نكرر التشغيل لحماية موارد الاستضافة.';
            return $request->expectsJson() ? response()->json(['status' => 'skipped', 'message' => $message]) : back()->with('info', $message);
        }

        Artisan::call('security:health-check');
        $message = 'سيدي، اكتمل الفحص الأمني والتشغيلي بنجاح.';

        return $request->expectsJson() ? response()->json(['status' => 'completed', 'message' => $message]) : back()->with('success', $message);
    }

    public function runReport(): RedirectResponse
    {
        Gate::authorize('viewAny', SecurityEvent::class);
        Artisan::call('security:weekly-report');

        return back()->with('success', 'سيدي، أُنشئ تقرير الموقف وأُرسل إلى إشعارات الإدارة.');
    }

    public function previewCleanup(): RedirectResponse
    {
        Gate::authorize('viewAny', SecurityEvent::class);
        Artisan::call('security:cleanup', ['--dry-run' => true]);

        return back()->with('info', trim(Artisan::output()));
    }

    public function runCleanup(): RedirectResponse
    {
        Gate::authorize('viewAny', SecurityEvent::class);
        Artisan::call('security:cleanup');

        return back()->with('success', trim(Artisan::output()));
    }
}
