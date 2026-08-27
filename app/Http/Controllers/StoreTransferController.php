<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Services\StoreTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class StoreTransferController extends Controller
{
    public function __construct(private StoreTransferService $transfers)
    {
    }

    public function index(Request $request, Store $store)
    {
        $this->authorizeOwnerStore($store);
        $statuses = ['pending', 'completed', 'rejected', 'cancelled'];
        $status = in_array($request->get('status'), $statuses, true) ? $request->get('status') : null;

        $transfers = StoreTransfer::with(['senderStore', 'receiverStore', 'items.senderProduct', 'items.receiverProduct', 'createdBy', 'actionBy'])
            ->where(function ($query) use ($store) {
                $query->where('sender_store_id', $store->id)
                    ->orWhere('receiver_store_id', $store->id);
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        $receiverStoreIds = $transfers->getCollection()->pluck('receiver_store_id')->unique();
        $receiverProductsByStore = Product::whereIn('store_id', $receiverStoreIds)
            ->sellable()
            ->orderBy('name')
            ->get(['id', 'store_id', 'name', 'quantity', 'barcode', 'category_id'])
            ->groupBy('store_id');

        $transfers->getCollection()->each(function (StoreTransfer $transfer) use ($receiverProductsByStore) {
            $receiverProducts = $receiverProductsByStore->get($transfer->receiver_store_id, collect());
            $transfer->items->each(function ($item) use ($receiverProducts) {
                // يعرض الباحث كل منتجات البيع في المتجر المستلم، وليس قائمة اقتراحات محدودة.
                $item->setRelation('receiverSuggestions', $receiverProducts);
            });
        });

        $currentBusinessDate = now()->toDateString();
        return view('user.store-transfers.index', compact('store', 'transfers', 'status', 'statuses', 'currentBusinessDate'));
    }

    public function create(Store $store)
    {
        $this->authorizeOwnerStore($store);
        $user = auth('web')->user();
        $stores = $user->stores()->where('id', '!=', $store->id)->orderBy('name')->get(['id', 'name']);
        $products = Product::where('store_id', $store->id)
            ->sellable()
            ->orderBy('name')
            ->get(['id', 'name', 'quantity', 'product_type', 'is_splittable']);

        $currentBusinessDate = now()->toDateString();
        return view('user.store-transfers.create', compact('store', 'stores', 'products', 'currentBusinessDate'));
    }

    public function store(Request $request, Store $store)
    {
        $this->authorizeOwnerStore($store);
        $user = auth('web')->user();
        $storeIds = $user->stores()->pluck('id');

        $validated = $request->validate([
            'receiver_store_id' => ['required', Rule::in($storeIds->filter(fn ($id) => (int) $id !== (int) $store->id)->map(fn ($id) => (string) $id)->all())],
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
            $user,
            Carbon::parse($validated['business_date'])->toDateString()
        );

        return redirect()
            ->route('user.stores.transfers.index', $store->id)
            ->with('success', "تم إنشاء طلب النقل رقم #{$transfer->id} وخصم الكمية من المتجر المرسل بانتظار الاستلام.");
    }

    public function approve(Request $request, Store $store, StoreTransfer $transfer)
    {
        $this->authorizeOwnerStore($store);
        $this->authorizeTransferForStore($store, $transfer, 'receive');
        $validated = $request->validate([
            'receiver_product_id' => 'required|array',
            'receiver_product_id.*' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $transfer->receiver_store_id))],
            'business_date' => $this->currentMonthDateRules(),
        ]);

        $this->transfers->approveTransfer($transfer, $validated['receiver_product_id'], auth('web')->user(), false, $validated['business_date']);

        return back()->with('success', 'تم اعتماد النقل وإضافة الكمية للمتجر المستلم.');
    }

    public function ownerApprove(Request $request, Store $store, StoreTransfer $transfer)
    {
        $this->authorizeOwnerStore($store);
        $this->authorizeTransferForStore($store, $transfer, 'receive');
        $validated = $request->validate([
            'receiver_product_id' => 'required|array',
            'receiver_product_id.*' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('store_id', $transfer->receiver_store_id))],
            'business_date' => $this->currentMonthDateRules(),
        ]);

        $this->transfers->approveTransfer($transfer, $validated['receiver_product_id'], auth('web')->user(), true, $validated['business_date']);

        return back()->with('success', 'تم اعتماد النقل بواسطة المالك نيابة عن المستلم.');
    }

    public function reject(Request $request, Store $store, StoreTransfer $transfer)
    {
        $this->authorizeOwnerStore($store);
        $this->authorizeTransferForStore($store, $transfer, 'receive');
        $validated = $request->validate(['reason' => 'required|string|max:1000', 'business_date' => $this->currentMonthDateRules()]);
        $this->transfers->rejectTransfer($transfer, $validated['reason'], auth('web')->user(), $validated['business_date']);

        return back()->with('success', 'تم رفض طلب النقل وإرجاع الكمية للمتجر المرسل.');
    }

    public function cancel(Request $request, Store $store, StoreTransfer $transfer)
    {
        $this->authorizeOwnerStore($store);
        $this->authorizeTransferForStore($store, $transfer, 'send');
        $validated = $request->validate(['business_date' => $this->currentMonthDateRules()]);
        $this->transfers->cancelTransfer($transfer, auth('web')->user(), $validated['business_date']);

        return back()->with('success', 'تم إلغاء طلب النقل وإرجاع الكمية للمتجر المرسل.');
    }

    private function authorizeOwnerStore(Store $store): void
    {
        $user = auth('web')->user();

        if (!$user || (int) $store->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    private function authorizeTransferForStore(Store $store, StoreTransfer $transfer, string $direction): void
    {
        $expectedStoreId = $direction === 'send' ? $transfer->sender_store_id : $transfer->receiver_store_id;
        abort_unless((int) $store->id === (int) $expectedStoreId, 403);
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
