<?php

namespace App\Services;

use App\Models\InventoryCountSession;
use App\Models\InventoryCountSessionItem;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCountService
{
    public function saveAccountantCount(InventoryCountSessionItem $item, array $data, string $businessDate): InventoryCountSessionItem
    {
        return DB::transaction(function () use ($item, $data, $businessDate): InventoryCountSessionItem {
            $lockedItem = InventoryCountSessionItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $product = Product::withTrashed()->whereKey($lockedItem->product_id)->lockForUpdate()->firstOrFail();
            $capturedAt = now();

            $lockedItem->update($data + [
                'count_business_date' => $businessDate,
                'accountant_updated_at' => $capturedAt,
                // تحفظ لقطة النظام في نفس معاملة ووقت حفظ كمية المحاسب، ولا تعرض للمحاسب.
                'system_quantity_snapshot' => $this->systemQuantityInUnit($product, $data['unit_type']),
                'system_snapshot_at' => $capturedAt,
                'decision' => in_array($lockedItem->decision, ['returned', 'recounted'], true) ? 'recounted' : 'pending',
            ]);

            return $lockedItem->fresh('product');
        });
    }

    public function submitByAccountant(InventoryCountSession $session): InventoryCountSession
    {
        return DB::transaction(function () use ($session): InventoryCountSession {
            $locked = InventoryCountSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['sent_to_accountant', 'counting', 'returned_to_accountant'], true)) {
                throw ValidationException::withMessages(['session' => 'جلسة الجرد ليست متاحة للإرسال حاليًا.']);
            }
            $locked->load('items.product');
            $itemsToSubmit = $locked->status === 'returned_to_accountant'
                ? $locked->items->whereIn('decision', ['returned', 'recounted'])
                : $locked->items->where('decision', 'pending');
            if ($itemsToSubmit->isEmpty() || $itemsToSubmit->contains(fn ($item) => $item->accountant_quantity === null)) {
                throw ValidationException::withMessages(['items' => 'أدخل كمية الجرد لكل المنتجات قبل الإرسال للمالك.']);
            }

            foreach ($itemsToSubmit as $item) {
                // السجلات الجديدة تحمل لقطة وقت حفظ المحاسب؛ هذا الاستدراك للسجلات القديمة فقط.
                if ($item->system_quantity_snapshot === null || $item->system_snapshot_at === null) {
                    $item->update([
                        'system_quantity_snapshot' => $this->systemQuantityInUnit($item->product, $item->unit_type),
                        'system_snapshot_at' => $item->accountant_updated_at ?? now(),
                    ]);
                }
                $item->update(['decision' => 'pending']);
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

    public function approveItem(InventoryCountSessionItem $item, User $owner, string $approvalBusinessDate, ?float $ownerQuantity = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($item, $owner, $approvalBusinessDate, $ownerQuantity, $reason): void {
            $locked = InventoryCountSessionItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->load('session');
            if ($locked->decision !== 'pending' || $locked->session->owner_id !== $owner->id || ! in_array($locked->session->status, ['pending_owner', 'partially_approved', 'returned_to_accountant'], true)) {
                throw ValidationException::withMessages(['item' => 'هذا المنتج غير متاح للاعتماد.']);
            }
            if ($locked->accountant_quantity === null || ! $locked->count_business_date) {
                throw ValidationException::withMessages(['item' => 'لا توجد نتيجة جرد مكتملة لهذا المنتج.']);
            }
            if ($ownerQuantity !== null && trim((string) $reason) === '') {
                throw ValidationException::withMessages(['reason' => 'سبب تعديل كمية المحاسب مطلوب.']);
            }
            if (Carbon::parse($approvalBusinessDate)->startOfDay()->lt($locked->count_business_date->copy()->startOfDay())) {
                throw ValidationException::withMessages(['approval_business_date' => 'تاريخ الاعتماد لا يمكن أن يكون أقدم من يوم الجرد الذي سجله المحاسب.']);
            }

            $product = Product::whereKey($locked->product_id)->lockForUpdate()->firstOrFail();
            $approvedQuantity = $ownerQuantity ?? $locked->accountant_quantity;
            $approvedStoredQuantity = $this->quantityToStoredUnit($product, (float) $approvedQuantity, $locked->unit_type);
            $currentStoredQuantity = (float) $product->getRawOriginal('quantity');
            $currentCountQuantity = $this->systemQuantityInUnit($product, $locked->unit_type);
            $difference = $approvedStoredQuantity - $currentStoredQuantity;

            $locked->update([
                'owner_quantity' => $ownerQuantity,
                'owner_adjustment_reason' => $ownerQuantity !== null ? trim((string) $reason) : null,
                'decision' => $ownerQuantity !== null ? 'adjusted_approved' : 'approved',
                'approved_at' => now(),
            ]);

            $movementType = $difference < 0 ? 'decrease' : 'increase';
            $differenceLabel = abs($difference) < 0.0001
                ? 'تم تأكيد الجرد دون تغيير الكمية'
                : ($difference > 0
                    ? 'تمت إضافة فرق الجرد إلى المخزون'
                    : 'تم خصم فرق الجرد من المخزون');

            $product->update(['quantity' => $approvedStoredQuantity]);
            StockMovement::recordForProduct(
                $product,
                $movementType,
                abs($difference),
                $currentStoredQuantity,
                $approvedStoredQuantity,
                $owner->id,
                'تأكيد جرد المنتج — '.$differenceLabel.' — جلسة '.$locked->session->referenceCode(),
                abs((float) $approvedQuantity - $currentCountQuantity),
                $locked->unit_type,
                $approvalBusinessDate
            );

            InventoryLog::create([
                'store_id' => $locked->session->store_id,
                'user_id' => $owner->id,
                'product_id' => $locked->product_id,
                'inventory_count_session_item_id' => $locked->id,
                'quantity_change' => $difference,
                'quantity_snapshot' => $approvedQuantity,
                'type' => Product::INVENTORY_AUDIT_CONFIRMED_TYPE,
                'business_date' => $approvalBusinessDate,
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

    public function approveSelectedItems(InventoryCountSession $session, User $owner, array $itemIds, string $approvalBusinessDate): void
    {
        DB::transaction(function () use ($session, $owner, $itemIds, $approvalBusinessDate): void {
            $lockedSession = InventoryCountSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($lockedSession->owner_id !== $owner->id || ! in_array($lockedSession->status, ['pending_owner', 'partially_approved', 'returned_to_accountant'], true)) {
                throw ValidationException::withMessages(['items' => 'الجلسة غير متاحة للاعتماد حاليًا.']);
            }

            $items = $lockedSession->items()->whereIn('id', $itemIds)->where('decision', 'pending')->lockForUpdate()->get();
            if ($items->count() !== count(array_unique($itemIds))) {
                throw ValidationException::withMessages(['items' => 'بعض المنتجات المحددة غير متاحة للاعتماد.']);
            }

            foreach ($items as $item) {
                $this->approveItem($item, $owner, $approvalBusinessDate);
            }
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

    private function systemQuantityInUnit(Product $product, string $unitType): float
    {
        $stored = (float) $product->getRawOriginal('quantity');

        return match ($unitType) {
            'roll' => (float) $product->roll_length > 0 ? $stored / (float) $product->roll_length : $stored,
            'piece' => $product->is_splittable ? $stored * max(1, (int) $product->items_per_unit) : $stored,
            default => $stored,
        };
    }

    private function quantityToStoredUnit(Product $product, float $quantity, string $unitType): float
    {
        return match ($unitType) {
            'roll' => (float) $product->roll_length > 0 ? $quantity * (float) $product->roll_length : $quantity,
            'piece' => $product->is_splittable ? $quantity / max(1, (int) $product->items_per_unit) : $quantity,
            default => $quantity,
        };
    }
}
