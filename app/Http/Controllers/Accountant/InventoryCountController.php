<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\InventoryCountSession;
use App\Models\InventoryCountSessionItem;
use App\Models\Product;
use App\Services\InventoryCountService;
use App\Services\ShiftLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryCountController extends Controller
{
    public function index()
    {
        $accountant = auth('accountant')->user();
        $sessions = InventoryCountSession::with('store')->withCount('items')->where('accountant_id', $accountant->id)->latest()->paginate(15);
        return view('inventory-counts.accountant.index', compact('sessions'));
    }

    public function show(InventoryCountSession $inventoryCount)
    {
        $this->authorizeSession($inventoryCount);
        abort_unless(in_array($inventoryCount->status, ['sent_to_accountant', 'counting', 'returned_to_accountant'], true), 403);
        $inventoryCount->load([
            'items' => fn ($q) => ($inventoryCount->status === 'returned_to_accountant'
                ? $q->where('decision', 'returned')
                : $q->where('decision', 'pending'))->with('product'),
            'store',
        ]);
        return view('inventory-counts.accountant.show', ['session' => $inventoryCount]);
    }

    public function update(Request $request, InventoryCountSession $inventoryCount, InventoryCountSessionItem $item)
    {
        $this->authorizeSession($inventoryCount); abort_unless($item->inventory_count_session_id === $inventoryCount->id, 404);
        abort_unless(in_array($inventoryCount->status, ['sent_to_accountant', 'counting', 'returned_to_accountant'], true), 403);
        $item->loadMissing('product');
        $data = $request->validate([
            'accountant_quantity' => 'required|numeric|min:0',
            'unit_type' => ['required', Rule::in($this->allowedUnits($item->product))],
            'accountant_note' => 'nullable|string|max:1000',
        ]);
        $businessDate = app(ShiftLifecycleService::class)->currentShiftContext($inventoryCount->store_id)['business_date'];
        $item->update($data + ['count_business_date' => $businessDate, 'accountant_updated_at' => now(), 'decision' => 'pending']);
        $inventoryCount->update(['status' => 'counting']);
        return back()->with('success', 'تم حفظ كمية المنتج وتسجيل اليوم تلقائيًا.');
    }

    public function submit(InventoryCountSession $inventoryCount, InventoryCountService $service)
    {
        $this->authorizeSession($inventoryCount); $service->submitByAccountant($inventoryCount);
        return redirect()->route('accountant.inventory-counts.index')->with('success', 'تم إرسال نتائج الجرد للمالك.');
    }

    private function authorizeSession(InventoryCountSession $session): void
    {
        abort_unless((int) $session->accountant_id === (int) auth('accountant')->id(), 403);
    }

    private function allowedUnits(Product $product): array
    {
        if ($product->product_type === 'fractional') {
            return ['roll', 'meter'];
        }

        if ($product->is_splittable) {
            return ['kit', 'piece'];
        }

        return ['piece'];
    }
}
