<?php

namespace App\Services;

use App\Models\SecurityEvent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecurityEventService
{
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', 'token', '_token', 'authorization', 'cookie', 'secret', 'api_key'];

    public function record(string $code, string $category, string $severity, string $title, array $context = []): SecurityEvent
    {
        $request = app()->bound('request') ? request() : null;
        $sourceIp = $context['source_ip'] ?? $request?->ip();
        $route = $context['route'] ?? $request?->route()?->uri();
        $fingerprint = hash('sha256', implode('|', [$code, $sourceIp, $route, $context['subject'] ?? '']));
        $now = now();

        return DB::transaction(function () use ($code, $category, $severity, $title, $context, $request, $sourceIp, $route, $fingerprint, $now) {
            $event = SecurityEvent::query()
                ->where('fingerprint', $fingerprint)
                ->open()
                ->where('last_seen_at', '>=', $now->copy()->subHours(24))
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $evidence = $this->redact($context['evidence'] ?? []);

            if ($event) {
                $event->update([
                    'occurrences' => $event->occurrences + 1,
                    'last_seen_at' => $now,
                    'severity' => $this->highestSeverity($event->severity, $severity),
                    'confidence' => max($event->confidence, (int) ($context['confidence'] ?? 50)),
                    'evidence' => array_replace_recursive($event->evidence ?? [], $evidence),
                ]);

                return $event->refresh();
            }

            $actor = $context['actor'] ?? null;
            $target = $context['target'] ?? null;

            $event = SecurityEvent::create([
                'event_code' => $code,
                'category' => $category,
                'severity' => $severity,
                'confidence' => min(100, max(0, (int) ($context['confidence'] ?? 50))),
                'status' => 'new',
                'title' => $title,
                'description' => $context['description'] ?? null,
                'source_ip' => $sourceIp,
                'user_agent' => Str::limit((string) ($context['user_agent'] ?? $request?->userAgent()), 1000, ''),
                'route' => $route,
                'http_method' => $context['http_method'] ?? $request?->method(),
                'actor_type' => $actor instanceof Model ? $actor::class : null,
                'actor_id' => $actor instanceof Model ? $actor->getKey() : null,
                'target_type' => $target instanceof Model ? $target::class : null,
                'target_id' => $target instanceof Model ? $target->getKey() : null,
                'fingerprint' => $fingerprint,
                'evidence' => $evidence,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'detected_at' => $now,
            ]);

            if (in_array($severity, ['high', 'critical'], true)) {
                DB::afterCommit(function () use ($event, $title, $severity) {
                    $adminIds = User::query()->where('role', User::ROLE_ADMIN)->pluck('id')->all();

                    if ($adminIds !== []) {
                        Notification::create([
                            'sender_id' => null,
                            'sender_type' => 'system',
                            'target_type' => 'users',
                            'target_ids' => $adminIds,
                            'title' => $severity === 'critical' ? 'حالة استنفار أمني' : 'بلاغ أمني مرتفع',
                            'message' => $title.' — افتح مركز القيادة الأمنية لمراجعة التقرير.',
                            'template_key' => null,
                            'channel' => 'site',
                            'read_by' => [],
                            'data' => ['security_event_id' => $event->id],
                        ]);
                    }
                });
            }

            return $event;
        });
    }

    public function transition(SecurityEvent $event, User $admin, string $action, ?string $note = null, ?int $assignedTo = null): SecurityEvent
    {
        $transitions = [
            'acknowledge' => 'investigating',
            'contain' => 'contained',
            'verify_resolve' => 'resolved',
            'false_positive' => 'false_positive',
            'release_source' => 'investigating',
            'cancel_password_reset' => 'investigating',
            'reopen' => 'investigating',
        ];
        $to = $transitions[$action] ?? $event->status;

        return DB::transaction(function () use ($event, $admin, $action, $note, $assignedTo, $to) {
            if (in_array($action, ['contain', 'verify_resolve', 'false_positive', 'block_source', 'release_source', 'require_password_reset', 'cancel_password_reset', 'reopen'], true)) {
                $this->rejectUnless((bool) config('security_command_center.response_enabled'), 'أوامر الاستجابة معطلة حاليًا، بينما يبقى الرصد فعالًا.');
            }

            $from = $event->status;
            $changes = ['status' => $to];

            if ($action === 'acknowledge') {
                $changes += ['acknowledged_by' => $admin->id, 'assigned_to' => $admin->id, 'acknowledged_at' => now()];
            } elseif ($action === 'assign') {
                $changes['assigned_to'] = $assignedTo;
            } elseif ($action === 'add_note') {
                $changes = [];
            } elseif ($action === 'contain') {
                $this->rejectUnless(! in_array($event->status, ['resolved', 'false_positive'], true), 'لا يمكن احتواء بلاغ مغلق قبل إعادة فتحه.');
                $changes += ['contained_at' => now(), 'response_action' => 'manual_containment'];
            } elseif ($action === 'verify_resolve') {
                $this->rejectUnless($event->status === 'contained', 'يجب احتواء البلاغ قبل التحقق من الحل.');
                $changes += [
                    'resolved_at' => now(),
                    'verified_at' => now(),
                    'verified_by' => $admin->id,
                    'verification_note' => $note,
                    'resolution' => $note,
                ];
            } elseif ($action === 'false_positive') {
                $changes += ['resolved_at' => now(), 'resolution' => $note];
            } elseif ($action === 'block_source') {
                $this->rejectUnless((bool) $event->source_ip, 'لا يوجد عنوان مصدر صالح للتقييد.');
                $this->rejectUnless(! hash_equals($event->source_ip, (string) request()->ip()), 'لا يمكن للمدير تقييد عنوان اتصاله الحالي.');
                $expiresAt = now()->addMinutes(max(1, (int) config('security_command_center.block_minutes', 30)));
                Cache::put($this->blockKey($event->source_ip), true, $expiresAt);
                $changes += ['status' => 'contained', 'contained_at' => now(), 'response_action' => 'block_source', 'response_expires_at' => $expiresAt];
            } elseif ($action === 'release_source') {
                $this->rejectUnless($event->response_action === 'block_source' && $event->source_ip, 'لا يوجد تقييد مصدر قابل للرجوع لهذا البلاغ.');
                Cache::forget($this->blockKey($event->source_ip));
                $changes += ['response_action' => null, 'response_expires_at' => null];
            } elseif ($action === 'require_password_reset') {
                $targetUser = $event->target instanceof User ? $event->target : ($event->actor instanceof User ? $event->actor : null);
                $this->rejectUnless((bool) $targetUser, 'لا يرتبط البلاغ بحساب مستخدم يمكن فرض إعادة التعيين عليه.');
                $this->rejectUnless($targetUser->role !== User::ROLE_ADMIN || $targetUser->id !== $admin->id, 'لا يمكن للمدير فرض إعادة التعيين على حسابه من جلسته الحالية.');
                $targetUser->forceFill(['must_reset_password' => true, 'remember_token' => null])->save();
                $changes += ['status' => 'contained', 'contained_at' => now(), 'response_action' => 'require_password_reset'];
            } elseif ($action === 'cancel_password_reset') {
                $targetUser = $event->target instanceof User ? $event->target : ($event->actor instanceof User ? $event->actor : null);
                $this->rejectUnless((bool) $targetUser && $targetUser->must_reset_password, 'لا يوجد فرض إعادة تعيين قابل للرجوع لهذا البلاغ.');
                $targetUser->forceFill(['must_reset_password' => false])->save();
                $changes += ['response_action' => null];
            } elseif ($action === 'reopen') {
                $this->rejectUnless(in_array($event->status, ['resolved', 'false_positive'], true), 'يمكن إعادة فتح البلاغات المغلقة فقط.');
                $changes += [
                    'resolved_at' => null,
                    'verified_at' => null,
                    'verified_by' => null,
                    'verification_note' => null,
                    'resolution' => null,
                ];
            }

            if ($changes !== []) {
                $event->update($changes);
            }
            $event->activities()->create([
                'user_id' => $admin->id,
                'action' => $action,
                'from_status' => $from,
                'to_status' => $changes['status'] ?? $to,
                'note' => $note,
                'ip_address' => request()->ip(),
            ]);

            return $event->refresh();
        });
    }

    public function isBlocked(?string $ip): bool
    {
        return $ip ? Cache::has($this->blockKey($ip)) : false;
    }

    private function blockKey(string $ip): string { return 'security:block:'.hash('sha256', $ip); }

    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(Str::lower((string) $key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_string($value)) {
                $data[$key] = Str::limit($value, 2000, '…');
            }
        }

        return Arr::undot(Arr::dot($data));
    }

    private function highestSeverity(string $current, string $incoming): string
    {
        $weights = ['info' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        return ($weights[$incoming] ?? 0) > ($weights[$current] ?? 0) ? $incoming : $current;
    }

    private function rejectUnless(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['action' => $message]);
        }
    }
}
