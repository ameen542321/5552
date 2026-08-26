<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedItem;
use App\Models\SupportSession;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportActionController extends Controller
{
    public function index(SupportTicketService $tickets): View
    {
        $cancelledNow = $tickets->cancelExpiredUnanswered();
        $finishedTickets = SupportTicket::with(['owner', 'accountant'])
            ->whereIn('status', ['closed', 'cancelled'])
            ->latest('last_activity_at')
            ->paginate(25, ['*'], 'tickets_page')
            ->withQueryString();
        $endedSessions = SupportSession::whereNotNull('ended_at')->latest('ended_at')
            ->paginate(25, ['*'], 'sessions_page')->withQueryString();
        $expiredDeletedItems = ArchivedItem::with(['owner', 'store'])
            ->where('status', 'archived')
            ->whereNotNull('owner_restore_deadline')
            ->where('owner_restore_deadline', '<=', now())
            ->latest('owner_restore_deadline')
            ->paginate(25, ['*'], 'deleted_items_page')
            ->withQueryString();
        $completedDeletedItems = ArchivedItem::with(['owner', 'store'])
            ->whereIn('status', ['restored', 'purged'])
            ->latest('updated_at')
            ->paginate(25, ['*'], 'completed_items_page')
            ->withQueryString();
        $deletedTickets = SupportTicket::onlyTrashed()
            ->with(['owner', 'accountant'])
            ->latest('deleted_at')
            ->paginate(25, ['*'], 'deleted_tickets_page')
            ->withQueryString();

        return view('admin.support-actions.index', compact(
            'cancelledNow', 'finishedTickets', 'endedSessions', 'expiredDeletedItems',
            'completedDeletedItems', 'deletedTickets'
        ));
    }

    public function destroySession(SupportSession $session): RedirectResponse
    {
        abort_unless($session->ended_at, 422, 'لا يمكن حذف جلسة دعم ما زالت نشطة.');
        $reference = $session->ticket_reference;
        $session->delete();

        return back()->with('success', 'تم حذف سجل جلسة الدعم للتذكرة ' . $reference . '.');
    }

    public function destroyCompletedItem(ArchivedItem $archive): RedirectResponse
    {
        abort_unless(
            in_array($archive->status, ['restored', 'purged'], true),
            422,
            'لا يمكن حذف سجل عنصر ما زال ينتظر المراجعة.'
        );
        $reference = $archive->reference;
        $archive->delete();

        return back()->with('success', 'تم حذف سجل العملية ' . $reference . '.');
    }

    public function purgeTicket(Request $request, int $ticketId): RedirectResponse
    {
        $ticket = SupportTicket::onlyTrashed()->findOrFail($ticketId);
        abort_unless(
            $ticket->deleted_at?->lte(now()->subDays(SupportTicket::PERMANENT_DELETE_AFTER_DAYS)),
            422,
            'لا يمكن حذف التذكرة نهائيًا قبل مرور 90 يومًا على حذفها.'
        );
        $reference = $ticket->reference;
        $ticket->forceDelete();

        return back()->with('success', 'تم حذف التذكرة ' . $reference . ' نهائيًا.');
    }
}
