<?php

namespace App\Modules\PurchaseOrders\Pdf;

use App\Models\Store;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;

class PurchaseOrderPdfData
{
    public function build(Store $store, StorePurchaseOrder $order, ?string $requestedPdfMode): array
    {
        $isInventoryApprovalPdf = $requestedPdfMode === 'inventory' && $order->status === 'approved';
        $isReceiptPdf = ! $isInventoryApprovalPdf
            && $requestedPdfMode === 'receipt'
            && in_array($order->status, ['received', 'approved'], true);
        $isReceiptWorksheet = $requestedPdfMode === 'receipt' && $order->status === 'sent';

        $usesReceiptValues = $isReceiptPdf || $isInventoryApprovalPdf;
        $totalCost = $order->items->sum(fn ($item) => $usesReceiptValues
            ? (float) ($item->cost_price_at_receipt ?? $item->cost_price_at_order ?? 0)
            : (float) ($item->cost_price_at_order ?? 0));

        $customNotes = [];
        foreach ($order->items as $item) {
            $customNotes[$item->id] = $this->itemNote($item, $usesReceiptValues);
        }

        $filePrefix = $isInventoryApprovalPdf ? 'سجل_اعتماد_مخزني_' : ($isReceiptPdf ? 'سجل_استلام_' : ($isReceiptWorksheet ? 'مستند_استلام_' : 'طلبية_توريد_'));
        $file = $filePrefix . preg_replace('/[^\p{Arabic}\p{L}\p{N}\-_]+/u', '_', $store->name) . '_' . $order->id . '.pdf';

        return compact('totalCost', 'customNotes', 'isReceiptPdf', 'isInventoryApprovalPdf', 'isReceiptWorksheet', 'file');
    }

    private function itemNote($item, bool $isReceiptPdf): string
    {
        $product = $item->product;
        $quantity = $isReceiptPdf
            ? (float) ($item->quantity_received ?? $item->quantity_requested ?? 0)
            : (float) ($item->quantity_requested ?? 0);
        $cartonText = '';

        if ($product && $product->carton_qty > 0 && $quantity >= $product->carton_qty) {
            $cartonQty = (int) $product->carton_qty;
            $cartons = floor($quantity / $cartonQty);
            $pieces = $quantity % $cartonQty;
            $unitName = $product->is_splittable ? 'طقم' : 'حبة';

            $cartonText = $cartons . ' كرتون';
            if ($pieces > 0) {
                $cartonText .= ' و ' . $pieces . ' ' . $unitName;
            }
        }

        $note = trim((string) ($item->receipt_notes ?? ''));

        if ($cartonText !== '' && $note !== '') {
            return $cartonText . ' | ملاحظة: ' . $note;
        }

        return $cartonText !== '' ? $cartonText : $note;
    }
}
