<?php

namespace App\Modules\PurchaseOrders\Services;

use App\Models\Store;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Pdf\PurchaseOrderPdfData;
use App\Modules\PurchaseOrders\Support\PurchaseOrderItemSorter;
use App\Support\ArabicPdf as PDF;

class PurchaseOrderPdfService
{
    public function download(Store $store, StorePurchaseOrder $order, string $mode, bool $hidePrices = false)
    {
        $store->loadMissing(['user', 'accountants']);
        $order->load(['items.product', 'items.matchedProduct', 'store.user', 'store.accountants', 'accountant']);
        PurchaseOrderItemSorter::sortLoadedItemsByName($order);

        if ($mode === 'inventory-count') {
            return PDF::loadView('modules.purchase-orders.inventory-count-pdf', compact('store', 'order'))
                ->setOption('encoding', 'utf-8')
                ->download('مراجعة_'.str_replace(' ', '_', $order->displayName()).'.pdf');
        }

        $pdfData = (new PurchaseOrderPdfData())->build($store, $order, $mode);
        $totalCost = $pdfData['totalCost'];
        $customNotes = $pdfData['customNotes'];
        $isReceiptPdf = $pdfData['isReceiptPdf'];
        $isInventoryApprovalPdf = $pdfData['isInventoryApprovalPdf'];
        $isReceiptWorksheet = $pdfData['isReceiptWorksheet'];

        return PDF::loadView('modules.purchase-orders.pdf', compact(
            'store',
            'order',
            'totalCost',
            'customNotes',
            'isReceiptPdf',
            'isInventoryApprovalPdf',
            'isReceiptWorksheet',
            'hidePrices',
        ))->setOption('encoding', 'utf-8')->download($pdfData['file']);
    }
}
