<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;

class OperationActorContext
{
    public function __construct(private readonly SupportSessionService $supportSessions)
    {
    }

    public function operationalActor(): ?Authenticatable
    {
        return auth('web')->user() ?? auth('accountant')->user();
    }

    public function realActor(): ?Authenticatable
    {
        return $this->supportSessions->active()?->admin ?? $this->operationalActor();
    }

    public function isTechnicalSupport(): bool
    {
        return $this->supportSessions->active() !== null;
    }

    public function auditMetadata(array $metadata = []): array
    {
        $session = $this->supportSessions->active();
        if (! $session) {
            return $metadata;
        }

        return array_merge($metadata, [
            'performed_by_technical_support' => true,
            'support_session_id' => $session->id,
            'support_admin_id' => $session->admin_id,
            'support_target_type' => $session->target_type,
            'support_target_id' => $session->target_id,
            'support_target_name' => $session->target_name,
            'support_ticket_reference' => $session->ticket_reference,
        ]);
    }
}
