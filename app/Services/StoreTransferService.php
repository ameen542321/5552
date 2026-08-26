<?php

namespace App\Services;

use App\Models\Accountant;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Models\StoreTransferItem;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StoreTransferService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public function createTransfer(Store $senderStore, Store $receiverStore, array $items, ?string $notes, Model $actor, string $businessDate): StoreTransfer
    {
        $this->ensureSameOwner($senderStore, $receiverStore);
        $this->ensureActorCanUseStore($actor, $senderStore);

        if ((int) $senderStore->id === (int) $receiverStore->id) {
            throw ValidationException::withMessages(['receiver_store_id' => 'لا يمكن نقل المنتج إلى نفس المتجر.']);
        }

        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'يجب إضافة منتج واحد على الأقل إلى طلب النقل.']);
        }

        $productIds = array_map(static fn ($item) => (int) ($item['sender_product_id'] ?? 0), $items);
        if (count($productIds) !== count(array_unique($productIds))) {
            throw ValidationException::withMessages(['items' => 'لا يمكن تكرار المنتج نفسه داخل طلب النقل.']);
        }

        usort($items, static fn ($left, $right) => (int) $left['sender_product_id'] <=> (int) $right['sender_product_id']);

        return DB::transaction(function () use ($senderStore, $receiverStore, $items, $notes, $actor, $businessDate) {
            $transfer = StoreTransfer::create([
                'sender_store_id' => $senderStore->id,
                'receiver_store_id' => $receiverStore->id,
                'status' => self::STATUS_PENDING,
                'notes' => $notes,
                'created_by_type' => $actor::class,
                'created_by_id' => $actor->getKey(),
                'request_business_date' => $businessDate,
            ]);

            foreach ($items as $index => $item) {
                $lockedProduct = Product::query()->sellable()->whereKey($item['sender_product_id'])->lockForUpdate()->firstOrFail();
                $this->ensureProductBelongsToStore($lockedProduct, $senderStore);

                $quantity = (float) $item['quantity'];
                $unitType = (string) $item['unit_type'];
                $allowedUnits = $lockedProduct->product_type === 'fractional'
                    ? ['roll', 'meter', 'meters']
                    : ($lockedProduct->is_splittable ? ['kit', 'piece'] : ['unit']);
                if (!in_array($unitType, $allowedUnits, true)) {
                    throw ValidationException::withMessages(["items.{$index}.unit_type" => "وحدة النقل المحددة لا تناسب المنتج {$lockedProduct->name}."]);
                }
                $normalizedQuantity = $lockedProduct->normalizeQuantityByUnit($quantity, $unitType);
                if ($normalizedQuantity <= 0) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => 'الكمية المدخلة غير صالحة.']);
                }

                $senderStockBefore = (float) $lockedProduct->getRawOriginal('quantity');
                if (round($senderStockBefore, 4) < round($normalizedQuantity, 4)) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => "الكمية المتوفرة من {$lockedProduct->name} لا تكفي."]);
                }

                $lockedProduct->decreaseStock(
                    $quantity,
                    "نقل صادر إلى متجر {$receiverStore->name} - طلب رقم #{$transfer->id}",
                    $this->stockMovementUserId($actor),
                    $unitType,
                    $businessDate
                );

                StoreTransferItem::create([
                    'store_transfer_id' => $transfer->id,
                    'sender_product_id' => $lockedProduct->id,
                    'requested_quantity' => $quantity,
                    'normalized_quantity' => $normalizedQuantity,
                    'unit_type' => $unitType,
                    'cost_price' => (float) $lockedProduct->cost_price,
                    'sender_stock_before' => $senderStockBefore,
                    'sender_stock_after' => $senderStockBefore - $normalizedQuantity,
                ]);
            }

            return $transfer->load(['senderStore', 'receiverStore', 'items.senderProduct']);
        });
    }

    public function approveTransfer(StoreTransfer $transfer, array $receiverProductIds, Model $actor, bool $ownerOverride = false, ?string $businessDate = null): StoreTransfer
    {
        return DB::transaction(function () use ($transfer, $receiverProductIds, $actor, $ownerOverride, $businessDate) {
            $lockedTransfer = StoreTransfer::query()
                ->whereKey($transfer->id)
                ->where('status', self::STATUS_PENDING)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTransfer->load(['senderStore', 'receiverStore', 'items.senderProduct']);
            $this->ensureActorCanReceiveTransfer($actor, $lockedTransfer, $ownerOverride);

            foreach ($lockedTransfer->items as $item) {
                $receiverProductId = (int) ($receiverProductIds[$item->id] ?? $receiverProductIds[$item->sender_product_id] ?? 0);
                if ($receiverProductId <= 0) {
                    throw ValidationException::withMessages(['receiver_product_id' => 'يجب اختيار المنتج المقابل في المتجر المستلم قبل الموافقة.']);
                }

                $receiverProduct = Product::query()->sellable()->whereKey($receiverProductId)->lockForUpdate()->firstOrFail();
                $this->ensureProductBelongsToStore($receiverProduct, $lockedTransfer->receiverStore);

                $receiverBefore = (float) $receiverProduct->getRawOriginal('quantity');
                $note = $ownerOverride
                    ? "نقل وارد من متجر {$lockedTransfer->senderStore->name} باعتماد المالك نيابة عن المستلم - طلب رقم #{$lockedTransfer->id}"
                    : "نقل وارد من متجر {$lockedTransfer->senderStore->name} - طلب رقم #{$lockedTransfer->id}";

                $receiverProduct->increaseStock(
                    (float) $item->normalized_quantity,
                    $note,
                    $this->stockMovementUserId($actor),
                    'normalized',
                    $businessDate
                );

                $item->update([
                    'receiver_product_id' => $receiverProduct->id,
                    'receiver_stock_before' => $receiverBefore,
                    'receiver_stock_after' => $receiverBefore + (float) $item->normalized_quantity,
                ]);
            }

            $lockedTransfer->update([
                'status' => self::STATUS_COMPLETED,
                'action_by_type' => $actor::class,
                'action_by_id' => $actor->getKey(),
                'acted_at' => now(),
                'completed_at' => now(),
                'action_business_date' => $businessDate,
                'notes' => $ownerOverride
                    ? trim((string) $lockedTransfer->notes . "\nتم اعتماد النقل بواسطة المالك نيابة عن المتجر المستلم.")
                    : $lockedTransfer->notes,
            ]);

            $approvedTransfer = $lockedTransfer->fresh(['senderStore', 'receiverStore', 'items.senderProduct', 'items.receiverProduct', 'createdBy', 'actionBy']);

            if ($ownerOverride) {
                $this->notifyReceiverAccountantsAboutOwnerApproval($approvedTransfer);
            }

            return $approvedTransfer;
        });
    }

    public function rejectTransfer(StoreTransfer $transfer, string $reason, Model $actor, ?string $businessDate = null): StoreTransfer
    {
        return $this->returnTransferToSender($transfer, $actor, self::STATUS_REJECTED, $reason, $businessDate);
    }

    public function cancelTransfer(StoreTransfer $transfer, Model $actor, ?string $businessDate = null): StoreTransfer
    {
        return $this->returnTransferToSender($transfer, $actor, self::STATUS_CANCELLED, null, $businessDate);
    }

    public function suggestReceiverProducts(StoreTransferItem $item, int $receiverStoreId, int $limit = 8)
    {
        $item->loadMissing('senderProduct');
        $sender = $item->senderProduct;

        if (!$sender) {
            return collect();
        }

        return Product::query()
            ->where('store_id', $receiverStoreId)
            ->sellable()
            ->where(function ($query) use ($sender) {
                if ($sender->barcode) {
                    $query->orWhere('barcode', $sender->barcode);
                }

                $query->orWhere('name', $sender->name)
                    ->orWhere('name', 'like', '%' . $sender->name . '%');

                foreach ($this->nameTokens($sender->name) as $token) {
                    $query->orWhere('name', 'like', '%' . $token . '%');
                }

                if ($sender->category_id) {
                    $query->orWhere('category_id', $sender->category_id);
                }
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$sender->name])
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'quantity', 'barcode', 'category_id']);
    }

    public function markSeen(StoreTransfer $transfer, Accountant $accountant): StoreTransfer
    {
        if ((int) $transfer->receiver_store_id !== (int) $accountant->store_id) {
            abort(403);
        }

        $transfer->update(['receiver_seen_at' => now()]);

        return $transfer;
    }

    private function returnTransferToSender(StoreTransfer $transfer, Model $actor, string $status, ?string $reason, ?string $businessDate): StoreTransfer
    {
        return DB::transaction(function () use ($transfer, $actor, $status, $reason, $businessDate) {
            $lockedTransfer = StoreTransfer::query()
                ->whereKey($transfer->id)
                ->where('status', self::STATUS_PENDING)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTransfer->load(['senderStore', 'receiverStore', 'items.senderProduct']);
            $this->ensureActorCanReturnTransfer($actor, $lockedTransfer);

            foreach ($lockedTransfer->items as $item) {
                $senderProduct = Product::query()->sellable()->whereKey($item->sender_product_id)->lockForUpdate()->firstOrFail();
                $this->ensureProductBelongsToStore($senderProduct, $lockedTransfer->senderStore);

                $senderProduct->increaseStock(
                    (float) $item->normalized_quantity,
                    ($status === self::STATUS_REJECTED ? 'إرجاع كمية نقل مرفوض' : 'إرجاع كمية نقل ملغي') . " - طلب رقم #{$lockedTransfer->id}",
                    $this->stockMovementUserId($actor),
                    'normalized',
                    $businessDate
                );
            }

            $payload = [
                'status' => $status,
                'action_by_type' => $actor::class,
                'action_by_id' => $actor->getKey(),
                'acted_at' => now(),
                'action_business_date' => $businessDate,
            ];

            if ($status === self::STATUS_REJECTED) {
                $payload['rejection_reason'] = $reason;
                $payload['rejected_at'] = now();
            } else {
                $payload['cancelled_at'] = now();
            }

            $lockedTransfer->update($payload);

            return $lockedTransfer->fresh(['senderStore', 'receiverStore', 'items.senderProduct', 'items.receiverProduct', 'createdBy', 'actionBy']);
        });
    }

    private function notifyReceiverAccountantsAboutOwnerApproval(StoreTransfer $transfer): void
    {
        $accountantIds = Accountant::where('store_id', $transfer->receiver_store_id)->pluck('id')->all();

        if (empty($accountantIds)) {
            return;
        }

        $itemsSummary = $transfer->items
            ->map(fn (StoreTransferItem $item) => ($item->product_name_snapshot ?? $item->senderProduct?->name ?? 'منتج') . ' (' . rtrim(rtrim(number_format((float) $item->requested_quantity, 3, '.', ''), '0'), '.') . ' ' . ($item->unit_label_snapshot ?? $item->unit_type) . ')')
            ->implode('، ');

        try {
            NotificationService::send([
                'sender_id' => $transfer->action_by_id,
                'sender_type' => 'user',
                'target_type' => 'accountants',
                'target_ids' => $accountantIds,
                'title' => 'اعتماد نقل مخزني بواسطة المالك',
                'message' => "قام المالك باعتماد استلام نقل مخزني رقم #{$transfer->id} من متجر {$transfer->senderStore?->name} نيابة عن متجرك. المنتجات: {$itemsSummary}.",
                'template_key' => 'store_transfer_owner_approved',
                'channel' => 'site',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send store transfer owner approval notification.', [
                'transfer_id' => $transfer->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function ensureSameOwner(Store $senderStore, Store $receiverStore): void
    {
        if ((int) $senderStore->user_id !== (int) $receiverStore->user_id) {
            throw ValidationException::withMessages(['receiver_store_id' => 'لا يمكن النقل بين متاجر لا تتبع نفس المالك.']);
        }
    }

    private function ensureProductBelongsToStore(Product $product, Store $store): void
    {
        if ((int) $product->store_id !== (int) $store->id) {
            throw ValidationException::withMessages(['product_id' => 'المنتج لا يتبع المتجر المحدد.']);
        }
    }

    private function ensureActorCanUseStore(Model $actor, Store $store): void
    {
        if ($actor instanceof User && (int) $store->user_id === (int) $actor->id) {
            return;
        }

        if ($actor instanceof Accountant && (int) $actor->store_id === (int) $store->id) {
            return;
        }

        abort(403);
    }

    private function ensureActorCanReceiveTransfer(Model $actor, StoreTransfer $transfer, bool $ownerOverride): void
    {
        if ($ownerOverride) {
            if ($actor instanceof User && (int) $transfer->senderStore->user_id === (int) $actor->id && (int) $transfer->receiverStore->user_id === (int) $actor->id) {
                return;
            }

            abort(403);
        }

        if ($actor instanceof Accountant && (int) $actor->store_id === (int) $transfer->receiver_store_id) {
            return;
        }

        if ($actor instanceof User && (int) $transfer->receiverStore->user_id === (int) $actor->id) {
            return;
        }

        abort(403);
    }

    private function ensureActorCanReturnTransfer(Model $actor, StoreTransfer $transfer): void
    {
        if ($actor instanceof User && (int) $transfer->senderStore->user_id === (int) $actor->id) {
            return;
        }

        if ($actor instanceof Accountant && in_array((int) $actor->store_id, [(int) $transfer->sender_store_id, (int) $transfer->receiver_store_id], true)) {
            return;
        }

        abort(403);
    }

    private function stockMovementUserId(Model $actor): ?int
    {
        return $actor instanceof User ? (int) $actor->id : null;
    }

    private function nameTokens(string $name): array
    {
        return collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }
}
