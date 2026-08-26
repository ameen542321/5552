<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\User;
use App\Services\LogService;
use App\Services\SupportSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportSessionController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = \App\Models\SupportSession::query()
            ->with('admin:id,name,email')
            ->when($request->input('status') === 'active', fn ($query) => $query->whereNull('ended_at'))
            ->when($request->input('status') === 'ended', fn ($query) => $query->whereNotNull('ended_at'))
            ->latest('started_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.support-sessions.index', compact('sessions'));
    }

    public function owner(Request $request, User $user, SupportSessionService $service): RedirectResponse
    {
        $validated = $this->validateStartRequest($request);
        $admin = $request->user('web');
        $session = $service->start($admin, $user, $validated['reason'], $request, $validated['ticket_reference'] ?? null);

        app(LogService::class)->add('support_session_started', 'بدأ الدعم التقني جلسة بصفة المالك.', $user, [
            'support_session_id' => $session->id,
            'support_admin_id' => $admin->id,
            'support_ticket_reference' => $session->ticket_reference,
            'support_role' => 'owner',
            'reason' => $session->reason,
        ]);

        return redirect()->route('user.dashboard')->with('success', 'بدأت جلسة الدعم التقني بصفة المالك.');
    }

    public function accountant(Request $request, Accountant $accountant, SupportSessionService $service): RedirectResponse
    {
        $validated = $this->validateStartRequest($request);
        $admin = $request->user('web');
        $session = $service->start($admin, $accountant, $validated['reason'], $request, $validated['ticket_reference'] ?? null);

        app(LogService::class)->add('support_session_started', 'بدأ الدعم التقني جلسة بصفة المحاسب.', $accountant, [
            'support_session_id' => $session->id,
            'support_admin_id' => $admin->id,
            'support_ticket_reference' => $session->ticket_reference,
            'support_role' => 'accountant',
            'reason' => $session->reason,
        ]);

        return redirect()->route('accountant.dashboard')->with('success', 'بدأت جلسة الدعم التقني بصفة المحاسب.');
    }

    public function stop(Request $request, SupportSessionService $service): RedirectResponse
    {
        $session = $service->active($request);
        abort_unless($session, 403);

        app(LogService::class)->add('support_session_ended', 'أنهى الدعم التقني جلسة الدعم.', $session->target, [
            'support_session_id' => $session->id,
            'support_admin_id' => $session->admin_id,
            'support_ticket_reference' => $session->ticket_reference,
        ]);

        $service->stop($request);

        return redirect()->route('admin.users.index')->with('success', 'تم إنهاء الجلسة والعودة إلى لوحة الدعم التقني.');
    }

    private function validateStartRequest(Request $request): array
    {
        return $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'ticket_reference' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
