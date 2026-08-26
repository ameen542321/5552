<?php

namespace App\Services;

use App\Models\Accountant;
use App\Models\SupportSession;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SupportSessionService
{
    public const SESSION_KEY = 'technical_support_session_id';
    public const MAX_DURATION_HOURS = 4;

    public function start(
        User $admin,
        Authenticatable $target,
        string $reason,
        Request $request,
        ?string $ticketReference = null,
        ?SupportTicket $ticket = null
    ): SupportSession
    {
        if (! $admin->isAdmin()) {
            abort(403);
        }

        if (! $target instanceof User && ! $target instanceof Accountant) {
            throw ValidationException::withMessages(['target' => 'نوع الحساب المطلوب غير مدعوم.']);
        }

        if ($target instanceof User && $target->isAdmin()) {
            throw ValidationException::withMessages(['target' => 'لا يمكن بدء جلسة دعم لحساب الإدارة.']);
        }

        $expiredSessions = SupportSession::with('ticket')
            ->where('admin_id', $admin->id)
            ->whereNull('ended_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();
        foreach ($expiredSessions as $expiredSession) {
            $expiredSession->update(['ended_at' => now(), 'ended_ip' => $request->ip()]);
            if ($expiredSession->ticket) {
                $tickets = app(SupportTicketService::class);
                $tickets->event($expiredSession->ticket, 'session_ended', 'system', null, [
                    'support_session_id' => $expiredSession->id,
                    'reason' => 'maximum_duration_reached',
                ]);
                $tickets->close($expiredSession->ticket, 'system', null);
            }
        }

        if (SupportSession::where('admin_id', $admin->id)->whereNull('ended_at')->exists()) {
            throw ValidationException::withMessages([
                'support_session' => 'توجد جلسة دعم نشطة بالفعل. أنهِ الجلسة الحالية قبل بدء جلسة أخرى.',
            ]);
        }

        $ownerId = $target instanceof Accountant ? $target->user_id : $target->id;
        $ticket ??= $ticketReference
            ? SupportTicket::where('reference', $ticketReference)->where('owner_id', $ownerId)->first()
            : null;
        if ($ticketReference && ! $ticket && SupportTicket::withTrashed()->where('reference', $ticketReference)->exists()) {
            throw ValidationException::withMessages([
                'ticket_reference' => 'رقم التذكرة لا يخص الحساب المحدد أو لم يعد متاحًا.',
            ]);
        }
        if ($ticket) {
            $expectedRole = $target instanceof Accountant ? 'accountant' : 'owner';
            $matchesTarget = (int) $ticket->owner_id === (int) $ownerId
                && $ticket->requested_role === $expectedRole
                && ($expectedRole === 'owner' || (int) $ticket->accountant_id === (int) $target->getAuthIdentifier());
            if (! $matchesTarget) {
                throw ValidationException::withMessages([
                    'ticket_reference' => 'رقم التذكرة لا يخص الحساب المحدد.',
                ]);
            }
            if (in_array($ticket->status, ['closed', 'cancelled', 'deleted'], true) || $ticket->trashed()) {
                throw ValidationException::withMessages([
                    'ticket_reference' => 'التذكرة منتهية؛ أعد فتحها قبل بدء جلسة جديدة.',
                ]);
            }
        }
        $availableReference = $ticketReference
            && ! SupportTicket::withTrashed()->where('reference', $ticketReference)->exists()
                ? $ticketReference
                : null;
        $ticket ??= SupportTicket::create([
            'reference' => $availableReference,
            'owner_id' => $ownerId,
            'accountant_id' => $target instanceof Accountant ? $target->id : null,
            'requested_role' => $target instanceof Accountant ? 'accountant' : 'owner',
            'subject' => 'جلسة دعم أنشأها النظام',
            'description' => $reason,
            'status' => 'in_progress',
            'created_by_support' => true,
        ]);
        $ticket->update(['status' => 'in_progress', 'closed_at' => null, 'last_activity_at' => now()]);

        $session = SupportSession::create([
            'support_ticket_id' => $ticket->id,
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
            'target_type' => $target::class,
            'target_id' => $target->getAuthIdentifier(),
            'target_name' => $target->name,
            'target_role' => $target instanceof Accountant ? 'accountant' : 'owner',
            'reason' => $reason,
            'ticket_reference' => $ticket->reference,
            'started_at' => now(),
            'expires_at' => now()->addHours(self::MAX_DURATION_HOURS),
            'started_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        app(SupportTicketService::class)->event($ticket, 'session_started', 'support', $admin->id, [
            'support_session_id' => $session->id,
            'target_role' => $target instanceof Accountant ? 'accountant' : 'owner',
        ]);

        $request->session()->put(self::SESSION_KEY, $session->id);
        Auth::guard('web')->logout();
        Auth::guard('accountant')->logout();

        $guard = $target instanceof Accountant ? 'accountant' : 'web';
        Auth::guard($guard)->login($target);

        return $session;
    }

    public function stop(Request $request): ?SupportSession
    {
        $session = $this->active($request);
        if (! $session) {
            return null;
        }

        $admin = User::withTrashed()->find($session->admin_id);
        abort_unless($admin && $admin->isAdmin(), 403);

        $session->update([
            'ended_at' => now(),
            'ended_ip' => $request->ip(),
        ]);
        if ($session->ticket) {
            $tickets = app(SupportTicketService::class);
            $tickets->event($session->ticket, 'session_ended', 'support', $admin->id, [
                'support_session_id' => $session->id,
            ]);
            $tickets->close($session->ticket, 'support', $admin->id);
        }

        Auth::guard('web')->logout();
        Auth::guard('accountant')->logout();
        $request->session()->forget(self::SESSION_KEY);
        Auth::guard('web')->login($admin);

        return $session;
    }

    public function active(?Request $request = null): ?SupportSession
    {
        $request ??= request();
        if (! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);
        if (! $id) {
            return null;
        }

        $session = SupportSession::with(['admin', 'target'])->find($id);
        if (! $session) {
            $request->session()->forget(self::SESSION_KEY);
            return null;
        }

        if ($session->ended_at) {
            $admin = User::withTrashed()->find($session->admin_id);
            Auth::guard('web')->logout();
            Auth::guard('accountant')->logout();
            $request->session()->forget(self::SESSION_KEY);
            if ($admin) {
                Auth::guard('web')->login($admin);
            }

            return null;
        }

        if ($session->expires_at?->isPast()) {
            $admin = User::withTrashed()->find($session->admin_id);
            $session->update(['ended_at' => now(), 'ended_ip' => $request->ip()]);
            if ($session->ticket) {
                $tickets = app(SupportTicketService::class);
                $tickets->event($session->ticket, 'session_ended', 'system', null, [
                    'support_session_id' => $session->id,
                    'reason' => 'maximum_duration_reached',
                ]);
                $tickets->close($session->ticket, 'system', null);
            }
            Auth::guard('web')->logout();
            Auth::guard('accountant')->logout();
            $request->session()->forget(self::SESSION_KEY);
            if ($admin) {
                Auth::guard('web')->login($admin);
            }

            return null;
        }

        $authenticatedTarget = Auth::guard('accountant')->user() ?? Auth::guard('web')->user();
        if (! $authenticatedTarget
            || $authenticatedTarget::class !== $session->target_type
            || (int) $authenticatedTarget->getAuthIdentifier() !== (int) $session->target_id) {
            return null;
        }

        return $session;
    }
}
