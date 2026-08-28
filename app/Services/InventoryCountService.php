<?php

namespace App\Services;

use App\Models\InventoryCountSession;
use App\Models\InventoryCountSessionItem;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCountService
{
    public function submitByAccountant(InventoryCountSession $session): InventoryCountSession
    {
        return DB::transaction(function () use ($session): InventoryCountSession {
            $locked = InventoryCountSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['sent_to_accountant', 'counting', 'returned_to_accountant'], true)) {
                throw ValidationException::withMessages(['session' => 'جلسة الجرد ليست متاحة للإرسال حاليًا.']);
            }
            $locked->load('items.product');
            if ($locked->items->contains(fn ($item) => $item->accountant_quantity === null)) {
                throw ValidationException::withMessages(['items' => 'أدخل كمية الجرد لكل المنتجات قبل الإرسال للمالك.']);
            }

            foreach ($locked->items->whereIn('decision', ['pending', 'returned']) as $item) {
                $item->update([
                    // نحول رصيد النظام إلى الوحدة التي عدّ بها المحاسب حتى تكون المقارنة عادلة.
                    'system_quantity_snapshot' => $this->systemQuantityInUnit($item),
                    'system_snapshot_at' => now(),
                    'decision' => 'pending',
                ]);
            }
            $locked->update(['status' => 'pending_owner', 'submitted_to_owner_at' => now()]);

            NotificationService::send([
                'sender_id' => $locked->accountant_id,
                'sender_type' => 'accountant',
                'target_type' => 'users',
                'target_ids' => [$locked->owner_id],
                'title' => 'نتيجة جرد بانتظار المراجعة',
                'message' => 'أرسل المحاسب نتائج جلسة ' . $locked->referenceCode() . ' للمراجعة.',
                'template_key' => 'inventory_count_submitted',
            ]);

            return $locked->fresh('items.product');
        });
    }

    public function approveItem(InventoryCountSessionItem $item, User $owner, ?float $ownerQuantity = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($item, $owner, $ownerQuantity, $reason): void {
            $locked = InventoryCountSessionItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->load('session');
            if ($locked->session->owner_id !== $owner->id || ! in_array($locked->session->status, ['pending_owner', 'partially_approved', 'returned_to_accountant'], true)) {
                throw ValidationException::withMessages(['item' => 'هذا المنتج غير متاح للاعتماد.']);
            }
            if ($locked->accountant_quantity === null || ! $locked->count_business_date) {
                throw ValidationException::withMessages(['item' => 'لا توجد نتيجة جرد مكتملة لهذا المنتج.']);
            }
            if ($ownerQuantity !== null && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => 'سبب تعديل كمية المحاسب مطلوب.']);
            }

            $locked->update([
                'owner_quantity' => $ownerQuantity,
                'owner_adjustment_reason' => $ownerQuantity !== null ? trim((string) $reason) : null,
                'decision' => $ownerQuantity !== null ? 'adjusted_approved' : 'approved',
                'approved_at' => now(),
            ]);

            // الاعتماد يوثق الجرد فقط ولا يغيّر رصيد المخزون؛ التسوية المخزنية تبقى عملية مستقلة وآمنة.
            InventoryLog::create([
                'store_id' => $locked->session->store_id,
                'user_id' => $owner->id,
                'product_id' => $locked->product_id,
                'inventory_count_session_item_id' => $locked->id,
                'quantity_change' => 0,
                'quantity_snapshot' => $ownerQuantity ?? $locked->accountant_quantity,
                'type' => Product::INVENTORY_AUDIT_CONFIRMED_TYPE,
                'business_date' => $locked->count_business_date,
            ]);

            $this->refreshSessionStatus($locked->session);
        });
    }

    public function returnItem(InventoryCountSessionItem $item, User $owner, string $reason): void
    {
        DB::transaction(function () use ($item, $owner, $reason): void {
            $locked = InventoryCountSessionItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->load('session');
            if ($locked->session->owner_id !== $owner->id || ! in_array($locked->session->status, ['pending_owner', 'partially_approved', 'returned_to_accountant'], true)) {
                throw ValidationException::withMessages(['item' => 'هذا المنتج غير متاح للإعادة.']);
            }
            $locked->update(['decision' => 'returned', 'owner_adjustment_reason' => trim($reason), 'attempt' => $locked->attempt + 1]);
            $locked->session->update(['status' => 'returned_to_accountant']);
        });
    }

    private function refreshSessionStatus(InventoryCountSession $session): void
    {
        $session->refresh();
        $remaining = $session->items()->whereNotIn('decision', ['approved', 'adjusted_approved'])->count();
        $hasReturned = $session->items()->where('decision', 'returned')->exists();
        $session->update($remaining === 0
            ? ['status' => 'approved', 'approved_at' => now()]
            : ['status' => $hasReturned ? 'returned_to_accountant' : 'partially_approved']);
    }

    private function systemQuantityInUnit(InventoryCountSessionItem $item): float
    {
        $product = $item->product;
        $stored = (float) $product->getRawOriginal('quantity');

        return match ($item->unit_type) {
            'roll' => (float) $product->roll_length > 0 ? $stored / (float) $product->roll_length : $stored,
            'piece' => $product->is_splittable ? $stored * max(1, (int) $product->items_per_unit) : $stored,
            default => $stored,
        };
    }
}
