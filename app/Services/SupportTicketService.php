<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\SupportTicketMessage;
use Illuminate\Support\Facades\DB;

class SupportTicketService
{
    public function cancelExpiredUnanswered(?int $ownerId = null): int
    {
        $tickets = SupportTicket::active()
            ->when($ownerId, fn ($query) => $query->where('owner_id', $ownerId))
            ->whereNull('responded_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $cancelled = 0;
        foreach ($tickets as $ticket) {
            DB::transaction(function () use ($ticket, &$cancelled): void {
                $locked = SupportTicket::lockForUpdate()->find($ticket->id);
                if (! $locked || $locked->responded_at || ! in_array($locked->status, SupportTicket::ACTIVE_STATUSES, true)) {
                    return;
                }
                $locked->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'closed_at' => now(),
                    'cancel_reason' => 'انتهت مهلة انتظار الرد البالغة 7 أيام.',
                    'last_activity_at' => now(),
                ]);
                $this->event($locked, 'cancelled', 'system', null);
                $cancelled++;
            });
        }

        return $cancelled;
    }

    public function addMessage(SupportTicket $ticket, string $role, ?int $actorId, string $message): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $role, $actorId, $message) {
            $locked = SupportTicket::lockForUpdate()->findOrFail($ticket->id);
            abort_if(in_array($locked->status, ['closed', 'cancelled'], true), 422, 'التذكرة مغلقة. أعد فتحها قبل إضافة رسالة.');

            $entry = $locked->messages()->create([
                'sender_role' => $role,
                'sender_id' => $actorId,
                'message' => $message,
            ]);
            $locked->update([
                'status' => $role === 'support' ? 'waiting_owner' : 'waiting_support',
                'last_activity_at' => now(),
                'owner_unread_count' => $role === 'support' ? $locked->owner_unread_count + 1 : $locked->owner_unread_count,
                'support_unread_count' => $role === 'owner' ? $locked->support_unread_count + 1 : $locked->support_unread_count,
                'support_response' => $role === 'support' ? $message : $locked->support_response,
                'responded_by' => $role === 'support' ? $actorId : $locked->responded_by,
                'responded_at' => $role === 'support' ? now() : $locked->responded_at,
            ]);
            $this->event($locked, $role === 'support' ? 'support_message' : 'owner_message', $role, $actorId);

            return $entry;
        });
    }

    public function event(SupportTicket $ticket, string $type, string $role, ?int $actorId, array $metadata = []): SupportTicketEvent
    {
        $ticket->update(['last_activity_at' => now()]);

        return $ticket->events()->create([
            'event_type' => $type,
            'actor_role' => $role,
            'actor_id' => $actorId,
            'metadata' => $metadata,
        ]);
    }

    public function markRead(SupportTicket $ticket, string $reader): void
    {
        $ticket->update([$reader === 'support' ? 'support_unread_count' : 'owner_unread_count' => 0]);
    }

    public function close(SupportTicket $ticket, string $role, ?int $actorId): void
    {
        $activeSessions = $ticket->sessions()->whereNull('ended_at')->get();
        foreach ($activeSessions as $session) {
            $session->update(['ended_at' => now()]);
            $this->event($ticket, 'session_ended', $role, $actorId, [
                'support_session_id' => $session->id,
                'reason' => 'ticket_closed',
            ]);
        }
        $ticket->update(['status' => 'closed', 'closed_at' => now(), 'last_activity_at' => now()]);
        $this->event($ticket, 'closed', $role, $actorId);
    }

    public function reopen(SupportTicket $ticket, string $role, ?int $actorId): void
    {
        $ticket->update([
            'status' => 'waiting_support',
            'closed_at' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'expires_at' => $ticket->responded_at
                ? $ticket->expires_at
                : now()->addDays(SupportTicket::UNANSWERED_EXPIRY_DAYS),
            'last_activity_at' => now(),
        ]);
        $this->event($ticket, 'reopened', $role, $actorId);
    }
}
