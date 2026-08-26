<?php

namespace App\Http\Controllers\Cashier;

use App\Helpers\LogHelper;
use App\Support\PaymentTypeLabel;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Employee;
use App\Models\SaleItem;
use App\Models\CreditSale;
use App\Services\ShiftLifecycleService;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\NotificationService;
use App\Support\ProductProfitCostCalculator;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class QuickSaleController extends Controller
{
    public function index()
    {
        $accountant = auth('accountant')->user();
        $store = $accountant->store;
        $tintProducts = $this->tintProductsForStore($accountant->store_id);
        $latestShiftOperation = $this->latestQuickSaleOperationForShift($accountant);
        $quickSaleSubmitToken = (string) Str::uuid();
        session(['quick_sale_submit_token' => $quickSaleSubmitToken]);

        return response()->view('cashier.quick-sale.index', [
            'tintProducts' => $tintProducts,
            'latestShiftOperation' => $latestShiftOperation,
            'quickSaleSubmitToken' => $quickSaleSubmitToken,
            'laborDescriptionOptions' => $store?->labor_description_options_list ?? ['تضليل', 'تجليد', 'شغل يد'],
            'hasApprovedTaxNumber' => filled($store?->tax_number),
            'hasAvailableTintProducts' => collect($tintProducts)->contains(
                fn (array $product) => $product['quantity'] > 0 && count($product['fractions']) > 0
            ),
        ])->withHeaders([
            // لا نستعيد نموذج بيع مملوءًا من BFCache عند الرجوع بالمتصفح.
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }


    /**
     * آخر عملية بيع مرتبطة بالشفت الحالي لعرضها كشريط تنبيهي فوق نافذة التضليل.
     */
    private function latestQuickSaleOperationForShift($accountant): ?array
    {
        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($accountant->store_id);

        $saleQuery = Sale::with(['items.product'])
            ->where('store_id', $accountant->store_id)
            ->where('accountant_id', $accountant->id)
            ->excludeManualInvoiceEntries()
            ->latest('id');

        if (! empty($shiftContext['daily_balance_id'])) {
            $saleQuery->where('daily_balance_id', $shiftContext['daily_balance_id']);
        } else {
            $saleQuery->whereDate('business_date', $shiftContext['business_date'] ?? now()->toDateString());
        }

        $sale = $saleQuery->first();
        if (! $sale) {
            return null;
        }

        $products = $sale->items
            ->map(fn ($item) => $item->historical_product_name)
            ->filter()
            ->unique()
            ->take(4)
            ->values();

        $description = trim((string) ($sale->description ?? ''));
        $summary = $products->isNotEmpty()
            ? $products->implode('، ')
            : ($description !== '' ? $description : 'عملية بيع بدون منتجات');

        return [
            'id' => $sale->id,
            'time' => optional($sale->created_at)->format('H:i'),
            'payment_type' => $this->getSaleTypeName($sale->sale_type),
            'paid_amount' => (float) $sale->paid_amount,
            'final_total' => (float) $sale->final_total,
            'summary' => $summary,
            'description' => $description,
        ];
    }

    /**
     * يعيد منتجات التضليل المجهزة لنافذة البيع دون أي تعديل على المخزون.
     */
    private function tintProductsForStore(int $storeId): array
    {
        return Product::query()
            ->with(['fractions' => function ($query) {
                $query->select('id', 'product_id', 'option_label', 'deduction_value', 'price')
                    ->orderBy('id');
            }])
            ->where('store_id', $storeId)
            ->whereHas('category', function ($query) use ($storeId) {
                $query->where('store_id', $storeId)
                    ->where('name', 'تضليل')
                    ->where('status', 'active');
            })
            ->where('product_type', 'fractional')
            ->where('status', 'active')
            ->sellable()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'price',
                'cost_price',
                'quantity',
                'roll_length',
                'waste_percentage',
            ])
            ->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'cost_price' => (float) ($product->cost_price ?? 0),
                    'quantity' => (float) $product->quantity,
                    'roll_length' => (float) $product->roll_length,
                    'waste_percentage' => (float) $product->waste_percentage,
                    'fractions' => $product->fractions->map(function ($fraction) {
                        return [
                            'id' => $fraction->id,
                            'option_label' => $fraction->option_label,
                            'deduction_value' => (float) $fraction->deduction_value,
                            'price' => (float) $fraction->price,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * يبقى هذا المسار للمعاينة المستقلة، بينما صفحة البيع تحمل البيانات مسبقًا.
     */
    public function tintPreviewProducts()
    {
        $accountant = auth('accountant')->user();
        if (! $accountant) {
            return response()->json([
                'message' => 'انتهت جلسة المحاسب. سجل الدخول ثم افتح المعاينة من شاشة البيع السريع.',
            ], 401);
        }

        return response()->json([
            'products' => $this->tintProductsForStore($accountant->store_id),
        ]);
    }

    public function creditPersons()
    {
        $storeId = auth('accountant')->user()->store_id;

        return \App\Models\Employee::where('store_id', $storeId)
            ->select('id', 'name')
            ->get();
    }

    public function submit(Request $request)
{
    // ✅ تسجيل بداية العملية
    Log::info('🚀 بداية عملية بيع جديدة', [
        'sale_type' => $request->sale_type,
        'labor_total' => $request->labor_total,
        'paid_amount' => $request->paid_amount,
        'has_partial_credit' => $request->has_partial_credit,
        'mixed_cash' => $request->mixed_cash,
        'mixed_card' => $request->mixed_card,
        'employee_id' => $request->employee_id,
    ]);

    $rules = [
        'items'         => 'required|json',
        'labor_total'   => 'required|numeric|min:0',
        'tax_rate'      => 'required|integer|in:0,15',
        'paid_amount'   => 'required|numeric|min:0',
        'sale_type'     => 'required|in:cash,card,credit,mixed',
        'employee_id'   => 'nullable|exists:employees,id',
        'description'   => 'nullable|string|max:500',
        'operation_name' => 'nullable|string|max:500',
        'credit_note'   => 'nullable|string|max:500',
        'tint_operation_names' => 'nullable|string|max:500',
        'has_invoice'   => 'nullable|in:0,1',
        'has_partial_credit' => 'nullable|in:0,1',
        'mixed_cash'    => 'nullable|numeric|min:0',
        'mixed_card'    => 'nullable|numeric|min:0',
        'debt_amount'   => 'nullable|numeric|min:0',
        'agreed_credit_total' => 'nullable|numeric|min:0',
        'client_operation_id' => 'nullable|uuid',
        'quick_sale_submit_token' => 'required|uuid',
    ];

    if ($request->sale_type === 'credit' || $request->has_partial_credit == 1) {
        $rules['credit_note'] = 'required_without:operation_name|nullable|string|max:500';
        $rules['operation_name'] = 'nullable|string|max:500';
    }

    $validated = $request->validate($rules);

    if (! hash_equals((string) session('quick_sale_submit_token'), (string) $validated['quick_sale_submit_token'])) {
        return redirect()
            ->route('accountant.quick-sale.index')
            ->with('info', 'تم تجاهل إرسال قديم من المتصفح. إذا كانت العملية قد نجحت فلن تسجل مرة ثانية، وابدأ عملية جديدة من الصفحة الحالية.');
    }

    $clientOperationId = (string) ($validated['client_operation_id'] ?? Str::uuid());

    DB::beginTransaction();

    try {
        $accountant = auth('accountant')->user();
        $storeId = $accountant->store_id;
        $userId = $accountant->user_id;
        $operationTimestamp = now();
        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($storeId);
        $operationBusinessDate = Carbon::parse($shiftContext['business_date'] ?? $operationTimestamp->toDateString());

        $existingSale = Sale::where('store_id', $storeId)
            ->where('accountant_id', $accountant->id)
            ->where('client_operation_id', $clientOperationId)
            ->first();

        if ($existingSale) {
            DB::rollBack();

            return $this->redirectAfterDuplicateQuickSale($request, $existingSale);
        }

        $items = json_decode($request->items, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('❌ خطأ في JSON', ['error' => json_last_error_msg()]);
            throw new \Exception('تنسيق بيانات المنتجات غير صحيح');
        }

        Log::info('📦 المنتجات المرسلة', ['items_count' => count($items)]);

        $tintOperationNames = collect($items)
            ->pluck('tint_group_label')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        if ($tintOperationNames->isEmpty() && $request->filled('tint_operation_names')) {
            $tintOperationNames = collect(explode(' - ', (string) $request->tint_operation_names))
                ->map(fn ($name) => trim($name))
                ->filter()
                ->unique()
                ->values();
        }

        // استخراج تفاصيل الدفع المختلط إذا وجدت
        $mixedCash = (float) ($request->mixed_cash ?? 0);
        $mixedCard = (float) ($request->mixed_card ?? 0);

        if ($request->sale_type === 'mixed') {
            // fallback للتوافق الخلفي: إذا لم تصل القيم المباشرة نقرأها من العنصر المؤقت
            if ($mixedCash <= 0 && $mixedCard <= 0) {
                $lastItem = end($items);
                if (isset($lastItem['_temp']) && isset($lastItem['payment_details'])) {
                    $mixedCash = (float) ($lastItem['payment_details']['cash'] ?? 0);
                    $mixedCard = (float) ($lastItem['payment_details']['card'] ?? 0);
                }
            }

            Log::info('💳 تفاصيل الدفع المختلط', [
                'mixed_cash' => $mixedCash,
                'mixed_card' => $mixedCard
            ]);

            // إزالة عنصر الدفع المؤقت
            $items = array_filter($items, function($item) {
                return !isset($item['_temp']);
            });

            // إعادة ترتيب المصفوفة
            $items = array_values($items);
        }

        $totalProfit = 0;
        $productsTotal = 0;
        $productErrors = [];
        $itemsWithDeductions = [];
        $hasFractionalItems = false;
        // قد تتضمن عملية التضليل أكثر من سطر يستخدم الرول نفسه (أمامي + خلفي مثلاً).
        // نجمع المحجوز لكل منتج قبل الخصم الفعلي حتى لا ينجح كل سطر منفرداً ويتجاوز
        // مجموع الأسطر المخزون المتاح. lockForUpdate يمنع عمليتي بيع متزامنتين من حجز الرول نفسه.
        $reservedQuantityByProduct = [];

        foreach ($items as $index => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = Product::query()
                ->where('id', $productId)
                ->where('store_id', $storeId)
                ->sellable()
                // لا نستبعد منتجات التضليل هنا لأن نافذة التضليل الخاصة داخل البيع السريع
                // ترسل هذه المنتجات عند بيعها؛ الاستبعاد مطلوب فقط في حقل البحث العادي.
                ->whereNotNull('price')
                ->where('price', '>', 0)
                ->whereNotNull('cost_price')
                ->where('quantity', '>', 0)
                ->lockForUpdate()
                ->first();
            if (! $product) {
                $productErrors[] = "تعذر العثور على أحد منتجات البيع داخل المتجر (رقم {$productId}).";
                Log::warning('⚠️ منتج غير موجود', ['product_id' => $productId, 'store_id' => $storeId]);
                continue;
            }

            $fractionId = $item['fraction_id'] ?? null;
            $saleUnit = $item['sale_unit'] ?? 'unit';
            $isCustom = ($fractionId === 'custom');
            $requestedQuantity = (float) ($item['quantity'] ?? 1);
            $quantityToDecrement = 0;
            $customMeters = null;

            // --- منطق الخصم المطور (أمتار + أطقم + عادي) ---

            // 1. إذا كان منتج رول (مجزأ)
            if ($product->product_type === 'fractional') {
                $hasFractionalItems = true;

                if ((float) $product->roll_length <= 0) {
                    $productErrors[] = "{$product->name}: طول الرول غير مضبوط، ولا يمكن حساب تكلفة الأمتار المباعة.";
                    continue;
                }

                if (abs($requestedQuantity - 1.0) > 0.0001) {
                    $productErrors[] = "{$product->name}: منتجات الرول تُباع كسطر مستقل لكل خيار، ولا يمكن تغيير الكمية.";
                    continue;
                }

                if ($isCustom) {
                    $customMeters = abs((float) ($item['custom_consumption'] ?? 0));
                    if ($customMeters <= 0 || (float) ($item['price'] ?? 0) <= 0) {
                        $productErrors[] = "{$product->name}: القص المخصص يتطلب أمتاراً وسعراً أكبر من صفر.";
                        continue;
                    }

                    $quantityToDecrement = $product->calculateFinalDeduction($customMeters, 'custom');
                } elseif ($fractionId && $fractionId !== '0') {
                    $fraction = $product->fractions()->find($fractionId);
                    if (! $fraction) {
                        $productErrors[] = "{$product->name}: خيار التجزئة المحدد غير موجود أو لا يتبع هذا المنتج.";
                        continue;
                    }

                    // مهم: قيمة deduction_value في خيارات الرول تمثل الأمتار الفعلية المستهلكة.
                    // مثال: خيار "زجاج أمامي" بقيمة 1.5 مع رول طوله 30م يخصم 1.5م فقط،
                    // وليس 1.5 × 30م. لذلك نمرر unitType = meter حتى لا تُعامل القيمة كنسبة من الرول.
                    $quantityToDecrement = $product->calculateFinalDeduction($fraction->deduction_value, 'meter');
                } else {
                    $productErrors[] = "{$product->name}: يجب تحديد خيار التجزئة أو اختيار قص مخصص.";
                    continue;
                }
            }
            // 2. إذا كان نظام أطقم وتم البيع "بالحبة"
            elseif ($product->is_splittable && $saleUnit === 'piece') {
                $quantityToDecrement = (float) $item['quantity'] / ($product->items_per_unit ?: 1);
            }
            // 3. المنتج العادي أو الطقم الكامل
            else {
                $quantityToDecrement = (float) $item['quantity'];
            }

            if ($quantityToDecrement <= 0) {
                $productErrors[] = "{$product->name}: كمية الخصم المحسوبة يجب أن تكون أكبر من صفر.";
                continue;
            }

            // التحقق من مجموع ما حُجز من المنتج داخل العملية نفسها، وليس من السطر منفرداً فقط.
            $alreadyReserved = (float) ($reservedQuantityByProduct[$product->id] ?? 0);
            $requiredForProduct = $alreadyReserved + $quantityToDecrement;
            if (round((float) $product->quantity, 4) < round($requiredForProduct, 4)) {
                $productErrors[] = "{$product->name}: المخزون غير كافٍ. المتوفر: "
                    . number_format((float) $product->quantity, 3)
                    . "، المطلوب للعملية: " . number_format($requiredForProduct, 3);
                Log::warning('⚠️ مخزون غير كاف', [
                    'product' => $product->name,
                    'available' => $product->quantity,
                    'already_reserved' => $alreadyReserved,
                    'line_required' => $quantityToDecrement,
                    'operation_required' => $requiredForProduct,
                ]);
                continue;
            }
            $reservedQuantityByProduct[$product->id] = $requiredForProduct;

            $itemUnitPrice = (float) ($item['price'] ?? 0);
            $itemQuantityForSale = $product->product_type === 'fractional' ? 1.0 : $requestedQuantity;
            $itemLineTotal = $itemUnitPrice * $itemQuantityForSale;

            $productsTotal += $itemLineTotal;

            $costUnitType = 'unit';
            $costQuantity = $quantityToDecrement;
            $costConsumedMeters = null;

            if ($product->product_type === 'fractional') {
                // cost_price للرول يمثل تكلفة الرول الكامل، بينما quantityToDecrement هنا أمتار مستهلكة.
                // لذلك تمرير custom_consumption يجعل الحاسبة تقسم الاستهلاك على roll_length وتحسب تكلفة الجزء المستخدم فقط.
                $costUnitType = 'meter';
                $costConsumedMeters = $quantityToDecrement;
            } elseif ($product->is_splittable && $saleUnit === 'piece') {
                $costUnitType = 'piece';
                $costQuantity = $requestedQuantity;
            }

            if ($product->product_type === 'fractional') {
                // هذه هي نقطة احتساب تكلفة الرول الوحيدة وقت البيع:
                // تكلفة السطر = (تكلفة الرول الكامل ÷ طول الرول) × الأمتار المخصومة بعد الهالك.
                // نحفظ الناتج في total_cost حتى لا تعيد كل صفحة تقرير تفسير تكلفة الرول بطريقتها.
                $itemCostTotal = (
                    (float) ($product->cost_price ?? 0)
                    / (float) $product->roll_length
                ) * $quantityToDecrement;
            } else {
                $itemCostTotal = ProductProfitCostCalculator::calculateItemCost($product, [
                    'quantity' => $costQuantity,
                    'unit_type' => $costUnitType,
                    'custom_consumption' => $costConsumedMeters,
                ]);
            }

            // حقول التكلفة في sale_items من نوع decimal(10,2)، لذلك نوحد الربح المحفوظ
            // مع القيمة التي ستقرأها التقارير من قاعدة البيانات.
            // الإضافة الصغيرة تمنع تمثيل الأعداد العشرية الثنائية من تحويل 39.545 مثلاً إلى 39.54.
            $itemCostTotal = round($itemCostTotal + 1e-9, 2, PHP_ROUND_HALF_UP);
            $totalProfit += ($itemLineTotal - $itemCostTotal);

            $itemsWithDeductions[] = [
                'product' => $product,
                'quantity_to_subtract' => $quantityToDecrement,
                'note' => ($product->is_splittable && $saleUnit === 'piece')
                    ? "بيع حبة من طقم (عدد {$requestedQuantity})"
                    : ($isCustom
                        ? "قص مخصص: $customMeters متر"
                        : ($product->product_type === 'fractional'
                            ? "خيار رول: {$fraction->option_label}"
                            : "بيع عادي")),
                'row_data' => [
                    'product_id'          => $item['product_id'],
                    'fraction_id'         => ($isCustom || !$fractionId) ? null : $fractionId,
                    'is_custom'           => $isCustom,
                    'custom_name'         => $isCustom ? ($item['custom_name'] ?? 'مخصص') : null,
                    'custom_consumption'  => $quantityToDecrement,
                    'custom_meters'       => $customMeters,
                    'roll_length_at_sale' => $product->roll_length,
                    'unit_type'           => $costUnitType,
                    'cost_price'          => (float) ($product->cost_price ?? 0),
                    'total_cost'          => $itemCostTotal,
                    'quantity'            => $itemQuantityForSale,
                    'price'               => $itemUnitPrice,
                    'total'               => $itemLineTotal,
                ]
            ];
        }

        if (!empty($productErrors)) {
            DB::rollBack();
            Log::warning('⛔ عملية ملغاة بسبب نقص المخزون', ['errors' => $productErrors]);
            return redirect()->back()->with('error', implode('<br>', $productErrors))->withInput();
        }

        $taxValue = ($productsTotal * $request->tax_rate) / 100;
        $finalTotal = round(($productsTotal + $taxValue) + $request->labor_total);

        if ($request->sale_type === 'credit') {
            $finalTotal = max($finalTotal, round((float) ($request->agreed_credit_total ?? 0)));
        }

        // تحديد المبلغ المدفوع حسب نوع البيع
        $paidAmount = round($request->paid_amount);
        $cashAmount = 0;
        $cardAmount = 0;

        if ($request->sale_type === 'mixed') {
            $cashAmount = round($mixedCash);
            $cardAmount = round($mixedCard);
            $paidAmount = $cashAmount + $cardAmount;
        } elseif ($request->sale_type === 'cash') {
            $cashAmount = $paidAmount;
        } elseif ($request->sale_type === 'card') {
            $cardAmount = $paidAmount;
        }

        $remaining = max(0, $finalTotal - $paidAmount);

        // التحقق من صحة الدفع
        $hasPartialCredit = $request->has_partial_credit == 1;

        // قيمة الأجل اليدوية (للآجل الجزئي) مستقلة عن إجمالي الفاتورة والمبلغ المستلم.
        $manualDebtAmount = round((float) ($request->debt_amount ?? 0));

        Log::info('💰 تفاصيل المبالغ', [
            'products_total' => $productsTotal,
            'tax_value' => $taxValue,
            'labor_total' => $request->labor_total,
            'final_total' => $finalTotal,
            'paid_amount' => $paidAmount,
            'cash_amount' => $cashAmount,
            'card_amount' => $cardAmount,
            'remaining' => $remaining,
            'has_partial_credit' => $hasPartialCredit
        ]);

        if ($request->sale_type !== 'credit' && !$hasPartialCredit && $paidAmount < $finalTotal) {
            DB::rollBack();
            $typeName = $this->getSaleTypeName($request->sale_type);
            Log::warning('⛔ عملية ملغاة: مبلغ مدفوع أقل من الإجمالي', [
                'paid' => $paidAmount,
                'final' => $finalTotal
            ]);
            return redirect()->back()
                ->with('error', "خطأ: المبلغ المدفوع ($paidAmount ريال) أقل من قيمة الفاتورة ($finalTotal ريال) في البيع $typeName")
                ->withInput();
        }

        if ($hasPartialCredit && !$request->employee_id) {
            DB::rollBack();
            Log::warning('⛔ عملية ملغاة: آجل جزئي بدون موظف');
            return redirect()->back()
                ->with('error', 'يجب اختيار الموظف لتسجيل المبلغ المتبقي كآجل')
                ->withInput();
        }

        if ($hasPartialCredit && in_array($request->sale_type, ['cash', 'card']) && $manualDebtAmount <= 0) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'يرجى إدخال قيمة الأجل للآجل الجزئي')
                ->withInput();
        }

        if ($hasPartialCredit && in_array($request->sale_type, ['cash', 'card']) && ($paidAmount + $manualDebtAmount) < $finalTotal) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', "خطأ محاسبي: مجموع المستلم ({$paidAmount}) + الأجل ({$manualDebtAmount}) أقل من إجمالي الفاتورة ({$finalTotal})")
                ->withInput();
        }

        if ($hasFractionalItems) {
            // تثبيت خاص بالعمليات التي تحتوي رول/تضليل فقط:
            // الربح النهائي للمنتجات يجب أن يطابق مجموع total_cost الذي سيُحفظ فعلياً
            // في sale_items، حتى لا تنشأ أي قيمة مختلفة في sales.profit.
            $storedItemsCost = array_sum(array_map(
                fn (array $itemData) => (float) ($itemData['row_data']['total_cost'] ?? 0),
                $itemsWithDeductions
            ));

            $totalProfit = round(
                ($productsTotal - $storedItemsCost) + 1e-9,
                2,
                PHP_ROUND_HALF_UP
            );
        }

        // أجرة اليد تبقى كما هي في النظام الحالي. وعملية أجرة اليد دون منتجات
        // لا تدخل شرط الرول أعلاه، لذلك لا يتغير مسارها أو احتسابها.
        $totalProfit += $request->labor_total;

        $operationName = mb_substr($tintOperationNames->implode(' - '), 0, 500);
        $creditNote = trim((string) ($request->operation_name ?? $request->credit_note ?? ''));
        $descriptionParts = collect([
            $operationName,
            trim((string) $request->description),
        ])->filter()->unique()->values();
        $saleDescription = mb_substr($descriptionParts->implode(' - '), 0, 500);

        // إنشاء سجل البيع بتاريخ النظام الحالي؛ تم إلغاء خيار تثبيت تاريخ مختلف من البيع السريع.
        $sale = new Sale([
            'store_id'         => $storeId,
            'accountant_id'    => $accountant->id,
            'employee_id'      => $hasPartialCredit ? $request->employee_id : ($request->sale_type === 'credit' ? $request->employee_id : null),
            'products_total'   => $productsTotal,
            'tax_rate'         => $request->tax_rate,
            'labor_total'      => $request->labor_total,
            'final_total'      => $finalTotal,
            'total'            => $finalTotal,
            'paid_amount'      => $paidAmount,
            'cash_amount'      => $cashAmount,
            'card_amount'      => $cardAmount,
            'remaining_amount' => $remaining,
            'sale_type'        => $request->sale_type,
            'has_partial_credit' => $hasPartialCredit,
            'has_invoice'      => $request->has_invoice == 1,
            'description'      => $saleDescription,
            'profit'           => $totalProfit,
            'business_date'    => $shiftContext['business_date'],
            'daily_balance_id' => $shiftContext['daily_balance_id'],
            'client_operation_id' => $clientOperationId,
        ]);
        $sale->save();

        Log::info('✅ تم إنشاء البيع', ['sale_id' => $sale->id]);

        // تسجيل الأجل إذا وجد
        $debtToRecord = ($hasPartialCredit && in_array($request->sale_type, ['cash', 'card']) && $manualDebtAmount > 0)
            ? $manualDebtAmount
            : $remaining;

        if ($debtToRecord > 0 && $request->employee_id) {
            $creditDescription = "أجل من عملية مبيعات رقم #" . $sale->id . ($hasPartialCredit ? " (آجل جزئي)" : "");

            $credit = CreditSale::create([
                'person_id'        => $request->employee_id,
                'person_type'      => 'App\\Models\\Employee',
                'store_id'         => $storeId,
                'sale_id'          => $sale->id,
                'amount'           => $debtToRecord,
                'remaining_amount' => $debtToRecord,
                'description'      => $creditDescription,
                'credit_note'      => $creditNote !== '' ? $creditNote : null,
                'date'             => $operationBusinessDate->toDateString(),
                'status'           => 'pending',
                'month'            => $operationBusinessDate->format('m-Y'),
                'added_by'         => $accountant->id,
            ]);

            Log::info('📝 تم تسجيل الأجل', [
                'credit_id' => $credit->id,
                'amount' => $debtToRecord,
                'employee_id' => $request->employee_id
            ]);

            $employee = Employee::find($request->employee_id);
            if ($employee) {
                LogHelper::add(
                    'employee_debt',
                    "قام المحاسب {$accountant->name} بإضافة أجل بقيمة {$debtToRecord} ريال على الموظف {$employee->name} من فاتورة #{$sale->id}",
                    $storeId
                );
            }
        }

        // تنفيذ الخصم الفعلي وتسجيل الحركة
        foreach ($itemsWithDeductions as $itemData) {
            $saleItem = $sale->items()->create($itemData['row_data']);
            $itemData['product']->decrement('quantity', $itemData['quantity_to_subtract']);

            $itemData['product']->stockMovements()->create([
                'store_id'   => $storeId,
                'user_id'    => $userId,
                'product_id' => $itemData['product']->id,
                'type'       => 'decrease',
                'quantity'   => $itemData['quantity_to_subtract'],
                'note'       => $itemData['note'] . " (مبيعات POS #{$sale->id})",
                'business_date' => $sale->business_date,
            ]);

            Log::info('📦 تم خصم المخزون', [
                'product' => $itemData['product']->name,
                'quantity' => $itemData['quantity_to_subtract']
            ]);
        }

        DB::commit();
        session()->forget('quick_sale_submit_token');

        // رسالة النجاح المناسبة
        $successMessage = $this->getSuccessMessage($request, $finalTotal, $paidAmount, $remaining, $hasPartialCredit, $cashAmount, $cardAmount, $debtToRecord);

        Log::info('🎉 تمت العملية بنجاح', ['sale_id' => $sale->id]);

        return ($request->has_invoice == 1)
            ? redirect()->route('accountant.quick-sale.invoice.create', ['sale' => $sale->id])->with('success', $successMessage)
            : redirect()->route('accountant.quick-sale.index')->with('success', $successMessage);

    } catch (\Exception $e) {
        DB::rollBack();

        if (! empty($clientOperationId ?? null)) {
            $duplicateSale = Sale::where('client_operation_id', $clientOperationId)->first();
            if ($duplicateSale) {
                return $this->redirectAfterDuplicateQuickSale($request, $duplicateSale);
            }
        }

        // ✅ تسجيل تفاصيل الخطأ كاملة
        Log::error('🔥 خطأ في عملية البيع', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_data' => [
                'sale_type' => $request->sale_type,
                'labor_total' => $request->labor_total,
                'paid_amount' => $request->paid_amount,
                'has_partial_credit' => $request->has_partial_credit,
                'mixed_cash' => $request->mixed_cash,
                'mixed_card' => $request->mixed_card,
                'employee_id' => $request->employee_id,
                'debt_amount' => $request->debt_amount,
            ]
        ]);

        return redirect()->back()->with('error', 'حدث خطأ تقني: ' . $e->getMessage())->withInput();
    }
}

    public function operationStatus(string $clientOperationId)
    {
        $accountant = auth('accountant')->user();
        if (! $accountant) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }

        $sale = Sale::where('store_id', $accountant->store_id)
            ->where('accountant_id', $accountant->id)
            ->where('client_operation_id', $clientOperationId)
            ->latest('id')
            ->first();

        if (! $sale) {
            return response()->json(['status' => 'not_found']);
        }

        return response()->json([
            'status' => 'completed',
            'sale_id' => $sale->id,
            'final_total' => (float) $sale->final_total,
            'invoice_url' => route('accountant.quick-sale.invoice.create', ['sale' => $sale->id]),
        ]);
    }

    private function redirectAfterDuplicateQuickSale(Request $request, Sale $sale)
    {
        $message = 'تم العثور على نفس عملية البيع مسجلة مسبقاً برقم #' . $sale->id . '؛ لم يتم تسجيلها مرة ثانية.';

        return ($request->has_invoice == 1 || $sale->has_invoice)
            ? redirect()->route('accountant.quick-sale.invoice.create', ['sale' => $sale->id])->with('success', $message)
            : redirect()->route('accountant.quick-sale.index')->with('success', $message);
    }

    private function getSuccessMessage($request, $finalTotal, $paidAmount, $remaining, $hasPartialCredit, $cashAmount, $cardAmount, $debtToRecord = 0)
    {
        $saleTypeName = $this->getSaleTypeName($request->sale_type);

        // حالة الدفع المختلط
        if ($request->sale_type === 'mixed') {
            $message = "✅ تم البيع المختلط بنجاح. نقداً: $cashAmount ريال، شبكة: $cardAmount ريال";
            if ($hasPartialCredit && $remaining > 0) {
                $employeeName = 'غير محدد';
                if ($request->employee_id) {
                    $employee = Employee::find($request->employee_id);
                    $employeeName = $employee ? $employee->name : 'غير محدد';
                }
                $message .= "، والمتبقي ($remaining ريال) كآجل على الموظف: $employeeName";
            }
            return $message;
        }

        // حالة الدفع النقدي أو الشبكة مع آجل جزئي
        if ($hasPartialCredit && ($remaining > 0 || $debtToRecord > 0)) {
            $employeeName = 'غير محدد';
            if ($request->employee_id) {
                $employee = Employee::find($request->employee_id);
                $employeeName = $employee ? $employee->name : 'غير محدد';
            }
            $debtAmount = $debtToRecord > 0 ? $debtToRecord : $remaining;
            return "✅ تم البيع {$saleTypeName} بنجاح. المبلغ المدفوع: {$paidAmount} ريال، والأجل المسجل ({$debtAmount} ريال) على الموظف: {$employeeName}";
        }

        // حالة البيع الآجل الكامل
        if ($request->sale_type === 'credit') {
            $employeeName = 'غير محدد';
            if ($request->employee_id) {
                $employee = Employee::find($request->employee_id);
                $employeeName = $employee ? $employee->name : 'غير محدد';
            }
            $debtAmount = $remaining > 0 ? $remaining : $finalTotal;
            return "✅ تم البيع الآجل بنجاح وتسجيل أجل بمبلغ ({$debtAmount} ريال) على الموظف: {$employeeName}";
        }

        // حالة الدفع الكامل (كاش أو شبكة)
        return "✅ تمت عملية البيع {$saleTypeName} بنجاح وتحديث المخزون.";
    }

    private function getSaleTypeName($type)
    {
        return PaymentTypeLabel::quickSaleMessageLabel($type);
    }
}
