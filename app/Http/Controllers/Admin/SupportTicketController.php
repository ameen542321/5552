<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportSessionService;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', 'in:open,waiting_support,replied,waiting_owner,in_progress,closed,cancelled'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tickets = SupportTicket::with(['owner', 'accountant'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);
                $query->where(fn ($item) => $item->where('reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', "%{$search}%")));
            })
            ->latest('last_activity_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.support-tickets.index', compact('tickets'));
    }

    public function show(SupportTicket $ticket, SupportTicketService $service): View
    {
        $service->markRead($ticket, 'support');
        $ticket->load(['owner', 'accountant', 'messages', 'events', 'sessions']);

        return view('admin.support-tickets.show', compact('ticket'));
    }

    public function respond(Request $request, SupportTicket $ticket, SupportTicketService $service): RedirectResponse
    {
        $validated = $request->validate(['support_response' => ['required', 'string', 'min:3', 'max:4000']]);
        $service->addMessage($ticket, 'support', $request->user('web')->id, $validated['support_response']);

        return back()->with('success', 'تم إرسال رد الدعم التقني على التذكرة ' . $ticket->reference);
    }

    public function start(Request $request, SupportTicket $ticket, SupportSessionService $service): RedirectResponse
    {
        abort_if(in_array($ticket->status, ['closed', 'cancelled', 'deleted'], true), 422);
        $target = $ticket->requested_role === 'accountant' ? $ticket->accountant : $ticket->owner;
        abort_unless($target, 422);
        $session = $service->start(
            $request->user('web'),
            $target,
            $ticket->description,
            $request,
            $ticket->reference,
            $ticket
        );
        $ticket->update(['status' => 'in_progress']);

        return redirect()->route($ticket->requested_role === 'accountant' ? 'accountant.dashboard' : 'user.dashboard')
            ->with('success', 'بدأت جلسة الدعم للتذكرة ' . $session->ticket_reference);
    }

    public function destroy(SupportTicket $ticket): RedirectResponse
    {
        abort_unless(
            in_array($ticket->status, ['closed', 'cancelled'], true),
            422,
            'لا يمكن حذف تذكرة لم تنتهِ بعد.'
        );
        abort_if($ticket->sessions()->whereNull('ended_at')->exists(), 422, 'لا يمكن حذف تذكرة لها جلسة دعم نشطة.');
        app(SupportTicketService::class)->event($ticket, 'deleted', 'support', auth('web')->id());
        $ticket->update(['status' => 'deleted', 'closed_at' => now()]);
        $ticket->delete();

        return back()->with('success', 'تم حذف طلب الدعم.');
    }

    public function close(Request $request, SupportTicket $ticket, SupportTicketService $service): RedirectResponse
    {
        $service->close($ticket, 'support', $request->user('web')->id);

        return back()->with('success', 'تم إغلاق التذكرة.');
    }

    public function reopen(Request $request, SupportTicket $ticket, SupportTicketService $service): RedirectResponse
    {
        $service->reopen($ticket, 'support', $request->user('web')->id);

        return back()->with('success', 'تمت إعادة فتح التذكرة.');
    }
}
