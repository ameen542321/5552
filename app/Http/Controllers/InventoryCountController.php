<?php

namespace App\Http\Controllers;

use App\Models\InventoryCountSession;
use App\Models\InventoryCountSessionItem;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\StockMovement;
use App\Services\InventoryCountService;
use App\Services\NotificationService;
use App\Support\ArabicPdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryCountController extends Controller
{
    private function ownerStore(Store $store): void { abort_unless((int) $store->user_id === (int) auth('web')->id(), 403); }

    public function index(Store $store)
    {
        $this->ownerStore($store);
        $sessions = InventoryCountSession::with('accountant')->withCount('items')->where('store_id', $store->id)->latest()->paginate(15);
        return view('inventory-counts.owner.index', compact('store', 'sessions'));
    }

    public function create(Request $request, Store $store)
    {
        $this->ownerStore($store);
        $editingSession = null;
        if ($request->filled('inventory_session')) {
            $editingSession = InventoryCountSession::where('store_id', $store->id)->where('owner_id', auth('web')->id())->where('status', 'draft')->findOrFail($request->integer('inventory_session'));
            if (! session()->has($this->selectionKey($store))) {
                session([$this->selectionKey($store) => $editingSession->items()->pluck('product_id')->all()]);
            }
        }
        $selected = session($this->selectionKey($store), []);
        $search = trim((string) $request->query('q'));
        $products = Product::query()->where('store_id', $store->id)->where(fn ($q) => $q->where('usage_type', '!=', Product::USAGE_TYPE_OWNER_PURCHASE)->orWhereNull('usage_type'))
            ->when($search, fn ($q) => $q->where(fn ($x) => $x->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->with('category')
            ->withMax(['inventoryLogs as last_audit_date' => fn ($q) => $q->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)], 'business_date')
            ->orderByRaw('last_audit_date IS NOT NULL')->orderBy('last_audit_date')->orderBy('name')->paginate(20)->withQueryString();
        $accountants = $store->accountants()->where('status', 'active')->orderBy('name')->get();
        return view('inventory-counts.owner.create', compact('store', 'products', 'selected', 'accountants', 'search', 'editingSession'));
    }

    public function updateSelection(Request $request, Store $store)
    {
        $this->ownerStore($store);
        $data = $request->validate(['page_product_ids' => 'array', 'page_product_ids.*' => 'integer', 'selected_ids' => 'array', 'selected_ids.*' => 'integer']);
        $existing = collect(session($this->selectionKey($store), []));
        $pageIds = collect($data['page_product_ids'] ?? [])->map(fn ($id) => (int) $id);
        $chosen = collect($data['selected_ids'] ?? [])->map(fn ($id) => (int) $id);
        $valid = Product::where('store_id', $store->id)->whereIn('id', $chosen)->pluck('id');
        session([$this->selectionKey($store) => $existing->diff($pageIds)->merge($valid)->unique()->values()->all()]);
        return back()->with('success', 'تم حفظ المنتجات المحددة. يمكنك الانتقال إلى صفحة أخرى دون فقدها.');
    }

    public function store(Request $request, Store $store)
    {
        $this->ownerStore($store);
        $data = $request->validate(['inventory_session' => 'nullable|integer', 'accountant_id' => ['required', Rule::exists('accountants', 'id')->where('store_id', $store->id)], 'note' => 'nullable|string|max:1000']);
        $ids = Product::where('store_id', $store->id)->whereIn('id', session($this->selectionKey($store), []))->pluck('id');
        // تعطيل مؤقت لحد الخمسة لاختبار الدورة الواقعية بمنتج واحد؛ يعاد إلى 5 بعد انتهاء التجربة.
        if ($ids->isEmpty()) throw ValidationException::withMessages(['products' => 'اختر منتجًا واحدًا على الأقل لإنشاء جلسة الجرد التجريبية.']);
        if (! ($data['inventory_session'] ?? null) && InventoryCountSession::where('store_id', $store->id)->whereIn('status', InventoryCountSession::OPEN_STATUSES)->count() >= 5) throw ValidationException::withMessages(['session' => 'لا يمكن فتح أكثر من خمس جلسات جرد في الوقت نفسه.']);

        $session = DB::transaction(function () use ($store, $data, $ids) {
            $session = ($data['inventory_session'] ?? null)
                ? InventoryCountSession::where('store_id', $store->id)->where('owner_id', auth('web')->id())->where('status', 'draft')->lockForUpdate()->findOrFail($data['inventory_session'])
                : InventoryCountSession::create(['store_id' => $store->id, 'owner_id' => auth('web')->id()]);
            $session->update(['accountant_id' => $data['accountant_id'], 'note' => $data['note'] ?? null]);
            $session->items()->whereNotIn('product_id', $ids)->delete();
            Product::whereIn('id', $ids)->get()->each(fn ($product) => $session->items()->firstOrCreate(['product_id' => $product->id], ['product_name_snapshot' => $product->name, 'product_description_snapshot' => $product->description, 'unit_type' => $this->defaultUnit($product)]));
            return $session;
        });
        session()->forget($this->selectionKey($store));
        return redirect()->route('user.stores.inventory-counts.show', [$store, $session])->with('success', 'تم إنشاء جلسة الجرد. راجع المنتجات ثم أرسلها للمحاسب.');
    }

    public function show(Store $store, InventoryCountSession $inventoryCount)
    {
        $this->ownerStore($store); $this->ensureSessionStore($inventoryCount, $store);
        $inventoryCount->load(['items.product', 'accountant']);
        $legacyAudits = InventoryLog::with('user')
            ->where('store_id', $store->id)
            ->whereIn('product_id', $inventoryCount->items->pluck('product_id'))
            ->where('type', Product::INVENTORY_AUDIT_CONFIRMED_TYPE)
            ->whereNull('inventory_count_session_item_id')
            ->latest('business_date')
            ->latest('created_at')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');
        // بعض عمليات الجرد القديمة كانت تسجل كحركة مخزون فقط قبل إنشاء سجل الجرد الموحد.
        $legacyAuditMovements = StockMovement::with('user')
            ->where('store_id', $store->id)
            ->whereIn('product_id', $inventoryCount->items->pluck('product_id'))
            ->where('note', 'like', 'تأكيد جرد المنتج%')
            ->latest('business_date')
            ->latest('created_at')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        return view('inventory-counts.owner.show', compact('store', 'legacyAudits', 'legacyAuditMovements') + ['session' => $inventoryCount]);
    }

    public function send(Store $store, InventoryCountSession $inventoryCount)
    {
        $this->ownerStore($store); $this->ensureSessionStore($inventoryCount, $store);
        abort_unless($inventoryCount->status === 'draft', 422);
        $inventoryCount->update(['status' => 'sent_to_accountant', 'sent_to_accountant_at' => now()]);
        NotificationService::send(['sender_id' => auth('web')->id(), 'sender_type' => 'user', 'target_type' => 'accountants', 'target_ids' => [$inventoryCount->accountant_id], 'title' => 'جلسة جرد جديدة', 'message' => 'أرسل المالك جلسة '.$inventoryCount->referenceCode().' لإدخال الكميات.', 'template_key' => 'inventory_count_requested']);
        return back()->with('success', 'تم إرسال الجلسة للمحاسب.');
    }

    public function decide(Request $request, Store $store, InventoryCountSession $inventoryCount, InventoryCountSessionItem $item, InventoryCountService $service)
    {
        $this->ownerStore($store); $this->ensureSessionStore($inventoryCount, $store); abort_unless($item->inventory_count_session_id === $inventoryCount->id, 404);
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'adjust', 'return'])], 'owner_quantity' => 'required_if:action,adjust|nullable|numeric|min:0', 'reason' => 'required_if:action,adjust,return|nullable|string|min:5|max:1000']);
        if ($data['action'] === 'return') { if (mb_strlen(trim((string) ($data['reason'] ?? ''))) < 5) throw ValidationException::withMessages(['reason' => 'اكتب سببًا واضحًا لإعادة المنتج للمحاسب.']); $service->returnItem($item, auth('web')->user(), $data['reason']); }
        else { $service->approveItem($item, auth('web')->user(), $data['action'] === 'adjust' ? (float) $data['owner_quantity'] : null, $data['reason'] ?? null); }
        return back()->with('success', 'تم حفظ قرارك للمنتج.');
    }

    public function bulkApprove(Request $request, Store $store, InventoryCountSession $inventoryCount, InventoryCountService $service)
    {
        $this->ownerStore($store);
        $this->ensureSessionStore($inventoryCount, $store);
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|integer|distinct',
        ], ['items.required' => 'حدد منتجًا واحدًا على الأقل للاعتماد.']);

        $service->approveSelectedItems($inventoryCount, auth('web')->user(), array_map('intval', $data['items']));

        return back()->with('success', 'تم اعتماد المنتجات المحددة وتسجيلها في سجل الجرد.');
    }

    public function destroy(Store $store, InventoryCountSession $inventoryCount)
    {
        $this->ownerStore($store); $this->ensureSessionStore($inventoryCount, $store);
        abort_unless(in_array($inventoryCount->status, ['draft', 'cancelled'], true), 422, 'يجب إلغاء الجلسة أولًا قبل حذفها.');
        $inventoryCount->delete();

        return redirect()->route('user.stores.inventory-counts.index', $store)->with('success', 'تم حذف جلسة الجرد من القائمة مع الاحتفاظ بسجلها التقني.');
    }

    public function cancel(Request $request, Store $store, InventoryCountSession $inventoryCount)
    {
        $this->ownerStore($store); $this->ensureSessionStore($inventoryCount, $store);
        abort_unless(in_array($inventoryCount->status, InventoryCountSession::OPEN_STATUSES, true), 422, 'لا يمكن إلغاء جلسة مكتملة أو ملغاة.');
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000'], [
            'reason.required' => 'اكتب سبب إلغاء جلسة الجرد.',
            'reason.min' => 'سبب الإلغاء يجب ألا يقل عن خمسة أحرف.',
        ]);
        $inventoryCount->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => trim($data['reason']),
        ]);

        if ($inventoryCount->accountant_id) {
            NotificationService::send(['sender_id' => auth('web')->id(), 'sender_type' => 'user', 'target_type' => 'accountants', 'target_ids' => [$inventoryCount->accountant_id], 'title' => 'إلغاء جلسة جرد', 'message' => 'ألغى المالك جلسة '.$inventoryCount->referenceCode().'.', 'template_key' => 'inventory_count_cancelled']);
        }

        return back()->with('success', 'تم إلغاء جلسة الجرد وإيقاف العمل عليها. يمكنك الآن حذفها من القائمة.');
    }

    public function pdf(Store $store, InventoryCountSession $inventoryCount)
    {
        $this->ownerStore($store); $this->ensureSessionStore($inventoryCount, $store); $inventoryCount->load(['items', 'accountant']);
        $pdf = PDF::loadView('inventory-counts.pdf', ['session' => $inventoryCount, 'store' => $store, 'issuedAt' => now()]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$inventoryCount->referenceCode().'.pdf"',
        ]);
    }

    private function selectionKey(Store $store): string { return 'inventory_count_selection_'.$store->id; }
    private function ensureSessionStore(InventoryCountSession $session, Store $store): void { abort_unless($session->store_id === $store->id, 404); }
    private function defaultUnit(Product $product): string { return $product->product_type === 'fractional' ? 'roll' : ($product->is_splittable ? 'kit' : 'piece'); }
}
