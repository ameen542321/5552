<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $owner = $request->user('web');
        app(SupportTicketService::class)->cancelExpiredUnanswered($owner->id);
        $tickets = SupportTicket::where('owner_id', $owner->id)->latest('last_activity_at')->paginate(20);
        $accountants = $owner->accountants()->with('store:id,name')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'store_id']);

        return view('user.support-tickets.index', compact('tickets', 'accountants'));
    }

    public function store(Request $request, SupportTicketService $service): RedirectResponse
    {
        $owner = $request->user('web');
        $service->cancelExpiredUnanswered($owner->id);
        $validated = $request->validate([
            'requested_role' => ['required', 'in:owner,accountant'],
            'accountant_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'category' => ['required', 'in:general,restore,accounting,inventory,account'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        $accountantId = null;
        if ($validated['requested_role'] === 'accountant') {
            $accountantId = $owner->accountants()->whereKey($validated['accountant_id'])->value('id');
            abort_unless($accountantId, 422);
        }

        $ticket = DB::transaction(function () use ($owner, $accountantId, $validated, $service) {
            $duplicate = SupportTicket::where('owner_id', $owner->id)
                ->where('requested_role', $validated['requested_role'])
                ->active()
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'requested_role' => $validated['requested_role'] === 'owner'
                        ? 'لديك طلب دخول كمالك ما زال معلقًا.'
                        : 'لديك طلب دخول كمحاسب ما زال معلقًا.',
                ]);
            }

            $ticket = SupportTicket::create([
                'owner_id' => $owner->id,
                'accountant_id' => $accountantId,
                'requested_role' => $validated['requested_role'],
                'category' => $validated['category'],
                'priority' => $validated['priority'],
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'status' => 'waiting_support',
                'last_activity_at' => now(),
            ]);
            $service->event($ticket, 'created', 'owner', $owner->id);
            $service->addMessage($ticket, 'owner', $owner->id, $validated['description']);

            return $ticket;
        });

        return back()->with('success', 'تم إرسال طلب الدعم. رقم التذكرة: ' . $ticket->reference);
    }

    public function show(Request $request, SupportTicket $ticket, SupportTicketService $service): View
    {
        $this->authorizeOwner($request, $ticket);
        $service->markRead($ticket, 'owner');
        $ticket->load(['messages', 'events', 'sessions']);

        return view('user.support-tickets.show', compact('ticket'));
    }

    public function message(Request $request, SupportTicket $ticket, SupportTicketService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $ticket);
        abort_unless($ticket->responded_at, 422, 'يمكن إضافة رسالة متابعة بعد أول رد من الدعم التقني.');
        $validated = $request->validate(['message' => ['required', 'string', 'min:2', 'max:4000']]);
        $service->addMessage($ticket, 'owner', $request->user('web')->id, $validated['message']);

        return back()->with('success', 'تمت إضافة رسالتك إلى التذكرة.');
    }

    public function close(Request $request, SupportTicket $ticket, SupportTicketService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $ticket);
        $service->close($ticket, 'owner', $request->user('web')->id);

        return back()->with('success', 'تم إغلاق التذكرة.');
    }

    public function reopen(Request $request, SupportTicket $ticket, SupportTicketService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $ticket);
        $service->reopen($ticket, 'owner', $request->user('web')->id);

        return back()->with('success', 'تمت إعادة فتح التذكرة.');
    }

    private function authorizeOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless((int) $ticket->owner_id === (int) $request->user('web')->id, 403);
    }
}
