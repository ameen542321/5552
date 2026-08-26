<?php

namespace App\Http\Controllers\Cashier;

use App\Models\Sale;
use App\Services\ShiftLifecycleService;
use App\Models\Store;
use App\Models\Invoice;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Support\ArabicPdf as PDF;

class InvoiceController extends Controller
{

    public function index(Request $request)
    {
        $storeId = optional(Auth::guard('accountant')->user())->store_id;
        abort_unless($storeId, 403);

        $query = Invoice::with('sale')
            ->whereHas('sale', fn($saleQuery) => $saleQuery->where('store_id', $storeId))
            ->latest();

        // البحث النصي
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_phone', 'LIKE', "%{$search}%");
            });
        }

        // فلترة حسب التاريخ (مهم جداً استخدام whereDate)
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // فلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // إحصائيات سريعة (للبطاقات العلوية)
        $scoped = Invoice::whereHas('sale', fn($saleQuery) => $saleQuery->where('store_id', $storeId));
        $totalInvoices = (clone $scoped)->count();
        $paidInvoices = (clone $scoped)->where('status', 'paid')->count();
        $pendingInvoices = (clone $scoped)->where('status', 'pending')->count();
        $totalAmount = (clone $scoped)->sum('total_amount');

        // تنفيذ الترقيم مع الحفاظ على الفلاتر في الروابط
        // افتراضياً 15 فاتورة في الصفحة
        $invoices = $query->paginate(15)->withQueryString();

        return view('invoices.index', compact(
            'invoices',
            'totalInvoices',
            'paidInvoices',
            'pendingInvoices',
            'totalAmount'
        ));
    }



    /**
     * فهرس فواتير المالك داخل متجر محدد.
     */
    public function ownerIndex(Store $store, Request $request)
    {
        $this->ensureOwnerStoreAccess($store);

        $query = Invoice::with('sale')
            ->whereHas('sale', fn($saleQuery) => $saleQuery->where('store_id', $store->id))
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('customer_phone', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $scoped = Invoice::whereHas('sale', fn($saleQuery) => $saleQuery->where('store_id', $store->id));

        $totalInvoices = (clone $scoped)->count();
        $paidInvoices = (clone $scoped)->where('status', 'paid')->count();
        $pendingInvoices = (clone $scoped)->where('status', 'pending')->count();
        $totalAmount = (clone $scoped)->sum('total_amount');

        $invoices = $query->paginate(15)->withQueryString();

        return view('invoices.index', compact(
            'store',
            'invoices',
            'totalInvoices',
            'paidInvoices',
            'pendingInvoices',
            'totalAmount'
        ));
    }

    /**
     * عرض نموذج إنشاء فاتورة يدوية للمالك.
     */
    public function ownerCreate(Store $store)
    {
        $this->ensureOwnerStoreAccess($store);

        return view('invoices.create', [
            'store' => $store,
            'isOwnerContext' => true,
        ]);
    }

    /**
     * حفظ فاتورة يدوية من سياق المالك.
     */
    public function ownerStore(Store $store, Request $request)
    {
        $this->ensureOwnerStoreAccess($store);

        $validated = $this->validateManualInvoiceRequest($request);

        return DB::transaction(function () use ($store, $request, $validated) {
            $invoice = $this->createManualInvoiceForStore($store, $validated, $request);

            return redirect()->route('user.stores.invoices.print', [$store->id, $invoice->id])
                ->with('success', 'تم حفظ الفاتورة بنجاح.');
        });
    }

    public function ownerShow(Store $store, Invoice $invoice)
    {
        $this->ensureOwnerStoreAccess($store);
        abort_unless(optional($invoice->sale)->store_id === $store->id, 404);
        $invoice->load('sale.items.product');

        return view('invoices.show', compact('store', 'invoice'));
    }


    public function ownerEdit(Store $store, Invoice $invoice)
    {
        $this->ensureOwnerStoreAccess($store);
        abort_unless(optional($invoice->sale)->store_id === $store->id, 404);
        $invoice->load('sale.items.product');

        return view('invoices.edit', compact('store', 'invoice'));
    }

    public function ownerPrint(Store $store, Invoice $invoice)
    {
        $this->ensureOwnerStoreAccess($store);
        abort_unless(optional($invoice->sale)->store_id === $store->id, 404);
        $invoice->load('sale.items.product', 'sale.store');

        return view('invoices.print', compact('invoice'));
    }

    public function ownerUpdate(Store $store, Request $request, Invoice $invoice)
    {
        $this->ensureOwnerStoreAccess($store);
        abort_unless(optional($invoice->sale)->store_id === $store->id, 404);

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'vehicle_type' => 'nullable|string|max:255',
            'plate_number' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'service_lines' => 'nullable|array',
            'service_lines.*' => 'nullable|string|max:255',
            'service_qtys' => 'nullable|array',
            'service_qtys.*' => 'nullable|numeric|min:0',
            'service_values' => 'nullable|array',
            'service_values.*' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'required|in:paid,pending,canceled,printed',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'nullable|integer|exists:sale_items,id',
            'item_quantities' => 'nullable|array',
            'item_quantities.*' => 'nullable|numeric|min:0',
            'item_prices' => 'nullable|array',
            'item_prices.*' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $invoice) {
            $invoiceData = $request->only([
                'customer_name',
                'customer_phone',
                'vehicle_type',
                'plate_number',
                'tax_number',
                'notes',
                'status',
            ]);
            $invoiceData['description'] = $this->buildEditableWorkDescription($request, $invoice->description);

            $invoice->update($invoiceData);

            $itemsSynced = $this->syncInvoiceItems($invoice, $request);
            if (!$itemsSynced) {
                $this->syncInvoiceManualAmounts($invoice, $request);
            }
        });

        return redirect()->route('user.stores.invoices.index', $store->id)
            ->with('success', 'تم تحديث الفاتورة بنجاح');
    }

    public function ownerDestroy(Store $store, Invoice $invoice)
    {
        $this->ensureOwnerStoreAccess($store);
        abort_unless(optional($invoice->sale)->store_id === $store->id, 404);

        DB::transaction(function () use ($invoice) {
            $this->deleteInvoiceAndRelatedData($invoice);
        });

        return redirect()->route('user.stores.invoices.index', $store->id)
            ->with('success', 'تم حذف الفاتورة بنجاح');
    }


    public function edit(Invoice $invoice)
    {
        abort_unless(optional($invoice->sale)->store_id === optional(Auth::guard('accountant')->user())->store_id, 404);
        $invoice->load('sale.items.product');
        return view('invoices.edit', compact('invoice'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        abort_unless(optional($invoice->sale)->store_id === optional(Auth::guard('accountant')->user())->store_id, 404);

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'vehicle_type' => 'required|string|max:255',
            'plate_number' => 'required|string|max:255',
            'tax_number' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'service_lines' => 'nullable|array',
            'service_lines.*' => 'nullable|string|max:255',
            'service_qtys' => 'nullable|array',
            'service_qtys.*' => 'nullable|numeric|min:0',
            'service_values' => 'nullable|array',
            'service_values.*' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'required|in:paid,pending,canceled,printed',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'nullable|integer|exists:sale_items,id',
            'item_quantities' => 'nullable|array',
            'item_quantities.*' => 'nullable|numeric|min:0',
            'item_prices' => 'nullable|array',
            'item_prices.*' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $invoice) {
            $description = $this->buildEditableWorkDescription($request, $invoice->description);
            $invoice->update([
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'vehicle_type' => $request->vehicle_type,
                'plate_number' => $request->plate_number,
                'tax_number' => $request->tax_number,
                'description' => $description,
                'notes' => $request->notes,
                'status' => $request->status,
            ]);

            $itemsSynced = $this->syncInvoiceItems($invoice, $request);
            if (!$itemsSynced) {
                $this->syncInvoiceManualAmounts($invoice, $request);
            }
        });

        return redirect()->route('accountant.invoices.index')
            ->with('success', 'تم تحديث الفاتورة بنجاح');
    }

    public function destroy(Invoice $invoice)
    {
        abort_unless(optional($invoice->sale)->store_id === optional(Auth::guard('accountant')->user())->store_id, 404);

        DB::transaction(function () use ($invoice) {
            $this->deleteInvoiceAndRelatedData($invoice);
        });

        return redirect()->route('accountant.invoices.index')
            ->with('success', 'تم حذف الفاتورة بنجاح');
    }




    public function create(Sale $sale)
    {
        // جلب العناصر المرتبطة بالبيع لضمان عرضها في صفحة الإنشاء
        $sale->load('items.product');
        return view('cashier.quick-sale.invoice-create', compact('sale'));
    }

    public function store(Request $request, Sale $sale)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'vehicle_type' => 'required|string|max:255',
            'plate_number' => 'required|string|max:255',
            'tax_number' => 'nullable|string|max:20', // هذا هو الرقم الضريبي للعميل
            'notes' => 'nullable|string',
            // أضفنا هذه الحقول لدعم نوع البيع يدوياً
            'sale_unit' => 'nullable|in:piece,meter,unit',
        ]);




        $invoiceNumber = \App\Models\Invoice::latest('id')->first();
        $lastNumber = $invoiceNumber ? ($invoiceNumber->id + 1) : 1001;
        $invoiceNumber = date('Ymd') . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);




        // // تأمين ترقيم الفواتير
        // $invoiceNumber = DB::transaction(function () {
        //     $lastNumber = Invoice::lockForUpdate()->max('invoice_number');
        //     return $lastNumber ? ($lastNumber + 1) : 1001;
        // });

        // --- الحسابات المالية الدقيقة (الضريبة على المنتجات فقط) ---
        $productsTotal = $sale->products_total; // صافي المنتجات
        $laborTotal = $sale->labor_total;    // صافي أجور اليد
        $taxRate = $sale->tax_rate;       // نسبة الضريبة (مثلاً 15)

        // 1. حساب قيمة الضريبة على المنتجات فقط
        $taxAmount = round($productsTotal * ($taxRate / 100), 2);

        // 2. الصافي (مجموع المنتج + اليد بدون ضريبة)
        $subtotal = $productsTotal + $laborTotal;

        // 3. الإجمالي النهائي (الصافي + الضريبة المحسوبة)
        $finalTotal = $subtotal + $taxAmount;

        $invoice = Invoice::create([
            'sale_id' => $sale->id,
            'invoice_number' => $invoiceNumber,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'vehicle_type' => $request->vehicle_type,
            'plate_number' => $request->plate_number,
            'tax_number' => $request->tax_number, // حفظ الرقم الضريبي للعميل هنا
            'notes' => $request->notes,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $finalTotal,
            'status' => 'printed',
        ]);

        // تحديث حالة عملية البيع لتوضيح أنها مفوترة
        $sale->update(['has_invoice' => true]);

        // ملاحظة: تأكد من صحة مسار الـ redirect حسب الـ Route المبرمج عندك
        return redirect()->route('accountant.quick-sale.invoice.print', $invoice->id);
    }

    public function print(Invoice $invoice)
    {
        // جلب بيانات البيع والمنتجات المرتبطة بالفاتورة للطباعة
        $invoice->load(['sale.items.product', 'sale.store']);
        return view('cashier.quick-sale.invoice-print', compact('invoice'));
    }

    public function downloadPDF($storeOrInvoice, ?Invoice $invoice = null)
    {
        if ($storeOrInvoice instanceof Invoice) {
            $invoice = $storeOrInvoice;
        } elseif ($invoice instanceof Invoice) {
            // owner route passes {store} then {invoice}
        } else {
            $invoice = Invoice::findOrFail($storeOrInvoice);
        }

        if (Auth::guard('accountant')->check()) {
            abort_unless(optional($invoice->sale)->store_id === optional(Auth::guard('accountant')->user())->store_id, 404);
        }

        if (Auth::guard('web')->check()) {
            abort_unless(optional(optional($invoice->sale)->store)->user_id === Auth::guard('web')->id(), 404);
        }

        $invoice->load(['sale.items.product', 'sale.store']);

        $forPdf = true;
        $pdfView = request()->routeIs('accountant.quick-sale.invoice.pdf')
            ? 'cashier.quick-sale.invoice-print'
            : 'invoices.print';

        return PDF::loadView($pdfView, compact('invoice', 'forPdf'))
            ->setOption('encoding', 'utf-8')
            ->setOption('enable-local-file-access', true)
            ->setOption('margin-top', '10mm')
            ->setOption('margin-bottom', '10mm')
            ->download('فاتورة-' . $invoice->invoice_number . '.pdf');
    }

    private function ensureOwnerStoreAccess(Store $store): void
    {
        abort_unless((int) $store->user_id === (int) Auth::guard('web')->id(), 403);
    }


    public function createInvoice()
    {
        // التوجه مباشرة لملف create.blade.php الموجود داخل مجلد dashboard/invoices
        return view('invoices.create');
    }

    public function storeInvoice(Request $request)
    {
        $validated = $this->validateManualInvoiceRequest($request);
        // ... داخل دالة storeInvoice ...

        try {
            return DB::transaction(function () use ($request, $validated) {
                $accountant = Auth::guard('accountant')->user() ?: Auth::guard('web')->user();
                $store = DB::table('stores')->where('id', $accountant?->store_id)->first();

                if (!$store) {
                    throw new \RuntimeException('لا يمكن تحديد متجر صالح لإصدار الفاتورة.');
                }

                $invoice = $this->createManualInvoiceForStore(
                    Store::findOrFail($store->id),
                    $validated,
                    $request,
                    $accountant?->id
                );

                $accountant = Auth::guard('accountant')->user() ?: Auth::guard('web')->user();

                $ownerId = $store->user_id ?? null; // هذا هو المالك المسؤول
                // 5. إرسال الإشعار للمالك (المستخدم)
                if ($ownerId && $accountant) {

                    $publicUrl = route('public.invoice.show', $invoice->id);
                    \App\Models\Notification::create([
                        'sender_id' => $accountant->id,
                        'sender_type' => 'accountant',
                        'target_type' => 'user',
                        'target_ids' => [$ownerId],
                        'title' => '🧾 فاتورة جديدة: ' . $invoice->invoice_number,
                        'message' => "قام المحاسب {$accountant->name} بإصدار فاتورة بمبلغ " . number_format((float) $invoice->total_amount, 2),
                        'template_key' => 'new_invoice',
                        // الرابط يوضع هنا ليتم معالجته في صفحة الإشعارات
                        'data' => [
                            'url' => $publicUrl, // الرابط العام الجديد
                            'invoice_id' => $invoice->id,
                        ],
                    ]);
                }


                return response()
                    ->view('invoices.print', compact('invoice'))
                    ->withHeaders(['X-Invoice-Created' => '1']);
            });

        } catch (\Exception $e) {
            Log::error('Invoice creation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->with('error', 'حدث خطأ أثناء حفظ الفاتورة، يرجى المحاولة مرة أخرى.');
        }
    }

    public function publicShow($id)
    {
        $invoice = \App\Models\Invoice::with('sale.store')->findOrFail($id);

        if (Auth::guard('accountant')->check()) {
            abort_unless(
                optional($invoice->sale)->store_id === optional(Auth::guard('accountant')->user())->store_id,
                403
            );
        } elseif (Auth::guard('web')->check()) {
            abort_unless(
                (int) optional(optional($invoice->sale)->store)->user_id === (int) Auth::guard('web')->id(),
                403
            );
        } else {
            abort(403);
        }

        return view('invoices.edit', compact('invoice'));
    }

    private function validateManualInvoiceRequest(Request $request): array
    {
        return $request->validate([
            'customer_name' => 'required|string|max:255',
            'subtotal' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'service_lines' => 'nullable|array',
            'service_lines.*' => 'nullable|string|max:255',
            'service_qtys' => 'nullable|array',
            'service_qtys.*' => 'nullable|numeric|min:0',
            'service_values' => 'nullable|array',
            'service_values.*' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'customer_phone' => 'nullable|string|max:20',
            'vehicle_type' => 'nullable|string|max:255',
            'plate_number' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:20',
            'sale_type' => 'required|in:cash,card,mixed,credit',
            'product_names' => 'nullable|array',
            'product_names.*' => 'nullable|string|max:255',
            'product_prices' => 'nullable|array',
            'product_prices.*' => 'nullable|numeric|min:0',
        ]);
    }

    private function createManualInvoiceForStore(Store $store, array $validated, Request $request, ?int $actorId = null): Invoice
    {
        $subtotal = (float) $validated['subtotal'];
        $taxRate = (float) $validated['tax_rate'];
        $taxAmount = $subtotal * ($taxRate / 100);
        $totalAmount = $subtotal + $taxAmount;
        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($store->id);

        $sale = Sale::create([
            'store_id' => $store->id,
            'total' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
            'sale_type' => $validated['sale_type'],
            'has_invoice' => true,
            'description' => 'manual_invoice_entry',
            'accountant_id' => $actorId ?? auth()->id(),
            'business_date' => $shiftContext['business_date'],
            'daily_balance_id' => $shiftContext['daily_balance_id'],
        ]);

        $lastInvoice = Invoice::latest('id')->first();
        $nextId = $lastInvoice ? ($lastInvoice->id + 1) : 1001;
        $invoiceNumber = date('Ymd') . '-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);

        return Invoice::create([
            'sale_id' => $sale->id,
            'invoice_number' => $invoiceNumber,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'vehicle_type' => $validated['vehicle_type'] ?? null,
            'plate_number' => $validated['plate_number'] ?? null,
            'tax_number' => $validated['tax_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'description' => $this->buildManualInvoiceDescription($validated, $request),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'total_amount' => $totalAmount,
            'status' => 'printed',
        ]);
    }

    private function buildManualInvoiceDescription(array $validated, Request $request): string
    {
        $manualDescription = trim((string) ($validated['description'] ?? ''));
        $serviceLines = collect($request->input('service_lines', []))
            ->map(fn($line) => trim((string) $line))
            ->values()
            ->all();
        $serviceValues = $request->input('service_values', []);
        $serviceQtys = $request->input('service_qtys', []);
        $names = $request->input('product_names', []);
        $prices = $request->input('product_prices', []);
        $manualItemsLines = [];

        foreach ($names as $index => $name) {
            $cleanName = trim((string) $name);
            if ($cleanName === '') {
                continue;
            }

            $priceValue = $prices[$index] ?? null;
            $hasPrice = $priceValue !== null && $priceValue !== '' && is_numeric($priceValue);

            $manualItemsLines[] = $hasPrice
                ? '- ' . $cleanName . ' (السعر: ' . number_format((float) $priceValue, 2) . ' ر.س)'
                : '- ' . $cleanName;
        }

        $serviceDescriptionLines = [];
        foreach ($serviceLines as $index => $line) {
            $cleanLine = trim((string) $line);
            if ($cleanLine === '') {
                continue;
            }

            $value = $serviceValues[$index] ?? null;
            $qty = (float) ($serviceQtys[$index] ?? 1);
            $hasValue = $value !== null && $value !== '' && is_numeric($value);
            $lineTotal = $hasValue ? ($qty * (float) $value) : 0;
            $qtyLabel = fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');

            $serviceDescriptionLines[] = $hasValue
                ? '- ' . $cleanLine . ' (الكمية: ' . $qtyLabel . ' × السعر: ' . number_format((float) $value, 2) . ' = ' . number_format($lineTotal, 2) . ' ر.س)'
                : '- ' . $cleanLine;
        }

        return collect([
            !empty($serviceDescriptionLines) ? "وصف العمل:\n" . implode("\n", $serviceDescriptionLines) : null,
            $manualDescription,
            !empty($manualItemsLines) ? "المنتجات المدخلة:\n" . implode("\n", $manualItemsLines) : null,
        ])->filter()->implode("\n\n");
    }

    private function syncInvoiceItems(Invoice $invoice, Request $request): bool
    {
        $sale = $invoice->sale;
        if (!$sale) {
            return false;
        }

        $itemIds = $request->input('item_ids', []);
        $itemQuantities = $request->input('item_quantities', []);
        $itemPrices = $request->input('item_prices', []);

        if (empty($itemIds)) {
            return false;
        }

        $saleItems = SaleItem::where('sale_id', $sale->id)
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        foreach ($itemIds as $index => $itemId) {
            $item = $saleItems->get((int) $itemId);
            if (!$item) {
                continue;
            }

            $quantity = (float) ($itemQuantities[$index] ?? $item->quantity ?? 0);
            $price = (float) ($itemPrices[$index] ?? $item->price ?? 0);
            $lineTotal = $quantity * $price;

            $item->update([
                'quantity' => $quantity,
                'price' => $price,
                'total' => $lineTotal,
            ]);
        }

        $productsTotal = (float) SaleItem::where('sale_id', $sale->id)->sum('total');
        $taxRate = (float) ($invoice->tax_rate ?? 0);
        $taxAmount = $productsTotal * ($taxRate / 100);
        $finalTotal = $productsTotal + $taxAmount;

        $invoice->update([
            'subtotal' => $productsTotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $finalTotal,
        ]);

        $sale->update([
            'products_total' => $productsTotal,
            'final_total' => $finalTotal,
            'total' => $finalTotal,
            'paid_amount' => $finalTotal,
            'remaining_amount' => 0,
        ]);

        return true;
    }

    private function syncInvoiceManualAmounts(Invoice $invoice, Request $request): void
    {
        $sale = $invoice->sale;
        if (!$sale) {
            return;
        }

        $subtotal = (float) $request->input('subtotal', $invoice->subtotal ?? 0);
        $taxRate = (float) $request->input('tax_rate', $invoice->tax_rate ?? 0);
        $taxAmount = $subtotal * ($taxRate / 100);
        $totalAmount = $subtotal + $taxAmount;

        $invoice->update([
            'tax_rate' => $taxRate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);

        $sale->update([
            'tax_rate' => $taxRate,
            'products_total' => $subtotal,
            'final_total' => $totalAmount,
            'total' => $totalAmount,
            'paid_amount' => $totalAmount,
            'remaining_amount' => 0,
        ]);
    }

    private function buildEditableWorkDescription(Request $request, ?string $fallback = null): ?string
    {
        $serviceLines = $request->input('service_lines', []);
        $serviceQtys = $request->input('service_qtys', []);
        $serviceValues = $request->input('service_values', []);
        $additionalDescription = trim((string) $request->input('description', ''));

        $formattedServiceLines = [];
        foreach ($serviceLines as $index => $line) {
            $cleanLine = trim((string) $line);
            if ($cleanLine === '') {
                continue;
            }

            $qty = (float) ($serviceQtys[$index] ?? 1);
            $value = $serviceValues[$index] ?? null;
            $hasValue = $value !== null && $value !== '' && is_numeric($value);
            $qtyLabel = fmod($qty, 1.0) === 0.0 ? (string) (int) $qty : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
            $lineTotal = $hasValue ? ($qty * (float) $value) : 0;

            $formattedServiceLines[] = $hasValue
                ? '- ' . $cleanLine . ' (الكمية: ' . $qtyLabel . ' × السعر: ' . number_format((float) $value, 2) . ' = ' . number_format($lineTotal, 2) . ' ر.س)'
                : '- ' . $cleanLine;
        }

        $descriptionParts = collect([
            !empty($formattedServiceLines) ? "وصف العمل:\n" . implode("\n", $formattedServiceLines) : null,
            $additionalDescription !== '' ? $additionalDescription : null,
        ])->filter()->values();

        if ($descriptionParts->isEmpty()) {
            return $fallback;
        }

        return $descriptionParts->implode("\n\n");
    }

    private function deleteInvoiceAndRelatedData(Invoice $invoice): void
    {
        $sale = $invoice->sale;
        if (!$sale) {
            $invoice->delete();
            return;
        }

        $isManualInvoice = $sale->description === 'manual_invoice_entry';
        if ($isManualInvoice) {
            // الفاتورة اليدوية أنشأت sale مخصصًا لها، لذا نحذف جميع السجلات التابعة بالكامل.
            $invoice->delete();
            $sale->items()->delete();
            $sale->creditSales()->delete();
            $sale->delete();
            return;
        }

        // الفاتورة المرتبطة ببيع نظامي: نحذف الفاتورة فقط ونبقي عملية البيع.
        $sale->update(['has_invoice' => false]);
        $invoice->delete();
    }
}
