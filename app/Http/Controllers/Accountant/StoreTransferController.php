<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Services\StoreTransferService;
use App\Services\ShiftLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreTransferController extends Controller
{
    public function __construct(private StoreTransferService $transfers)
    {
    }

    public function index(Request $request)
    {
        $accountant = auth('accountant')->user();
        $storeId = $accountant->store_id;
        $statuses = ['pending', 'completed', 'rejected', 'cancelled'];
        $status = in_array($request->get('status'), $statuses, true) ? $request->get('status') : null;

        $incoming = StoreTransfer::with(['senderStore', 'receiverStore', 'items.senderProduct', 'items.receiverProduct', 'createdBy', 'actionBy'])
            ->where('receiver_store_id', $storeId)
            // هذا القسم مخصص للعمل التشغيلي فقط؛ المكتمل لا يظهر تحت عنوان "بحاجة لمعالجة".
            ->where('status', StoreTransferService::STATUS_PENDING)
            ->latest()
            ->paginate(10, ['*'], 'incoming_page');

        $outgoingPending = StoreTransfer::with(['senderStore', 'receiverStore', 'items.senderProduct', 'items.receiverProduct', 'createdBy', 'actionBy'])
            ->where('sender_store_id', $storeId)
            ->where('status', StoreTransferService::STATUS_PENDING)
            ->latest()
            ->paginate(10, ['*'], 'outgoing_pending_page');

        $outgoingCompleted = StoreTransfer::with(['senderStore', 'receiverStore', 'items.senderProduct', 'items.receiverProduct', 'createdBy', 'actionBy'])
            ->where('sender_store_id', $storeId)
            ->where('status', StoreTransferService::STATUS_COMPLETED)
            ->latest()
            ->paginate(10, ['*'], 'outgoing_completed_page');

        $receiverProducts = Product::where('store_id', $storeId)
            ->sellable()
            ->orderBy('name')
            ->get(['id', 'name', 'quantity', 'barcode', 'category_id']);

        $incoming->getCollection()->each(function (StoreTransfer $transfer) use ($receiverProducts) {
            $transfer->items->each(function ($item) use ($receiverProducts) {
                // لا نحصر البحث في تطابق الاسم أو أول ثمانية منتجات.
                $item->setRelation('receiverSuggestions', $receiverProducts);
            });
        });

        $currentBusinessDate = $this->currentBusinessDate($accountant->store);

        return view('accountants.store-transfers.index', compact('incoming', 'outgoingPending', 'outgoingCompleted', 'receiverProducts', 'status', 'statuses', 'currentBusinessDate'));
    }

    public function create()
    {
        $accountant = auth('accountant')->user();
        $store = $accountant->store;
        $stores = Store::where('user_id', $store->user_id)
            ->where('id', '!=', $store->id)
            ->orderBy('name')
            ->get(['id', 'name']);
        $products = Product::where('store_id', $store->id)
            ->sellable()
            ->orderBy('name')
            ->get(['id', 'name', 'quantity', 'product_type', 'is_splittable']);

        $currentBusinessDate = $this->currentBusinessDate($store);

        return view('accountants.store-transfers.create', compact('store', 'stores', 'products', 'currentBusinessDate'));
    }

    public function store(Request $request)
    {
        $accountant = auth('accountant')->user();
        $store = $accountant->store;
        $receiverStoreIds = Store::where('user_id', $store->user_id)
            ->where('id', '!=', $store->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $validated = $request->validate([
            'receiver_store_id' => ['required', Rule::in($receiverStoreIds)],
            'items' => 'required|array|min:1',
            'items.*.sender_product_id' => ['required', 'distinct', Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $store->id)->where(function ($productQuery) { $productQuery->where('usage_type', Product::USAGE_TYPE_SALE)->orWhereNull('usage_type'); }))],
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_type' => 'required|string|in:unit,roll,meter,meters,piece,kit,default,normalized',
            'notes' => 'nullable|string|max:1000',
            'business_date' => $this->currentMonthDateRules(),
        ]);

        $transfer = $this->transfers->createTransfer(
            $store,
            Store::findOrFail($validated['receiver_store_id']),
            $validated['items'],
            $validated['notes'] ?? null,
            $accountant,
            $validated['business_date']
        );

        return redirect()
            ->route('accountant.transfers.index')
            ->with('success', "تم إنشاء طلب النقل رقم #{$transfer->id} وخصم الكمية من متجرك بانتظار استلام المتجر الآخر.");
    }

    public function approve(Request $request, StoreTransfer $transfer)
    {
        $accountant = auth('accountant')->user();
        $validated = $request->validate([
            'receiver_product_id' => 'required|array',
            'receiver_product_id.*' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $accountant->store_id))],
            'business_date' => $this->currentMonthDateRules(),
        ]);

        $this->transfers->approveTransfer($transfer, $validated['receiver_product_id'], $accountant, false, $validated['business_date']);

        return back()->with('success', 'تم استلام النقل وإضافة الكمية لمخزون متجرك.');
    }

    public function reject(Request $request, StoreTransfer $transfer)
    {
        $accountant = auth('accountant')->user();
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'business_date' => $this->currentMonthDateRules(),
        ]);
        $this->transfers->rejectTransfer($transfer, $validated['reason'], $accountant, $validated['business_date']);

        return back()->with('success', 'تم رفض النقل وإرجاع الكمية للمتجر المرسل.');
    }

    public function cancel(Request $request, StoreTransfer $transfer)
    {
        $accountant = auth('accountant')->user();
        $validated = $request->validate(['business_date' => $this->currentMonthDateRules()]);
        $this->transfers->cancelTransfer($transfer, $accountant, $validated['business_date']);

        return back()->with('success', 'تم إلغاء النقل وإرجاع الكمية للمتجر المرسل.');
    }

    public function markSeen(StoreTransfer $transfer)
    {
        $this->transfers->markSeen($transfer, auth('accountant')->user());

        return back()->with('success', 'تم تعليم إشعار النقل كمقروء.');
    }

    private function currentBusinessDate(Store $store): string
    {
        return (string) app(ShiftLifecycleService::class)->currentShiftContext($store)['business_date'];
    }

    private function currentMonthDateRules(): array
    {
        return [
            'required',
            'date',
            'after_or_equal:'.now()->startOfMonth()->toDateString(),
            'before_or_equal:'.now()->endOfMonth()->toDateString(),
        ];
    }
}
