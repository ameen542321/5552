<div id="{{ $modalId }}" class="ui-modal-backdrop hidden">
    <div class="ui-modal-panel ui-modal-panel-wide ui-modal-panel-transfer">
        <div class="ui-help-modal-header">
            <h2 class="text-xl font-bold ui-title">{{ $title }}</h2>
            <button type="button"
                    data-ui-hide="{{ $modalId }}"
                    class="ui-help-modal-close" aria-label="إغلاق نافذة {{ $title }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>

        @if($modalId === 'creditSalesDetailsModal')
            <div class="ui-help-modal-body space-y-3">
                @forelse($rows as $rowIndex => $row)
                    @php
                        $linkedSale = method_exists($row, 'resolveLinkedSale') ? $row->resolveLinkedSale() : null;
                        if ($linkedSale) {
                            $linkedSale->loadMissing('items.product');
                        }
                        $payments = collect($row->collection_payments ?? []);
                        $operationName = trim((string) ($row->credit_note ?? '')) !== '' ? trim((string) $row->credit_note) : 'عملية أجل #' . $row->id;
                        $rowDate = optional($row->date)->format('Y-m-d') ?? $row->date ?? optional($row->created_at)->format('Y-m-d') ?? '-';
                        $displayAmount = (float) ($row->amount ?? 0);
                        $displayAmountLabel = 'إجمالي الأجل';
                        $partialCollectionTotal = $payments->filter(fn ($payment) => !str_contains((string) ($payment['description'] ?? ''), 'تحصيل كامل'))->sum(fn ($payment) => (float) ($payment['amount'] ?? 0));
                        $linkedSaleId = method_exists($row, 'resolveLinkedSaleId') ? $row->resolveLinkedSaleId() : null;
                        $linkedSaleDate = $linkedSale
                            ? \Carbon\Carbon::parse($linkedSale->business_date ?: $linkedSale->created_at)->toDateString()
                            : ($rowDate !== '-' ? $rowDate : now()->toDateString());
                        $linkedSaleEditUrl = ($linkedSaleId && !empty($row->store_id))
                            ? route('user.stores.daily', $row->store_id) . '?' . http_build_query([
                                'date' => $linkedSaleDate,
                                'edit_sale' => $linkedSaleId,
                                'return_to' => request()->fullUrl(),
                            ])
                            : null;
                        $isOutsideCurrentMonth = \Carbon\Carbon::parse($linkedSaleDate)->format('Y-m') !== now()->format('Y-m');
                        $isLinkedShiftClosed = $linkedSale && !empty($linkedSale->daily_balance_id);
                        $closedShiftWarning = 'تنبيه: العملية مرتبطة بشفت مغلق، وقد يؤدي التعديل أو الحذف إلى فروقات حسابية في التقارير السابقة.';
                    @endphp

                    <details class="group ui-disclosure rounded-2xl ui-border ui-surface-muted-bg">
                        <summary class="cursor-pointer list-none p-4 ui-hover-surface transition">
                            <div class="grid grid-cols-1 gap-3 md:items-center">
                                <div class="ui-text-caption ui-text-muted">#{{ $rowIndex + 1 }}</div>
                                <div>
                                    <p class="font-bold ui-title">{{ $operationName }}</p>
                                </div>
                                <div>
                                    <p class="ui-text-caption ui-text-muted">{{ $displayAmountLabel }}</p>
                                    <p class="ui-status-info font-bold">{{ number_format($displayAmount, 2) }} ريال</p>
                                </div>
                                <div class="ui-text-soft text-sm">{{ $rowDate }}</div>
                            </div>
                        </summary>

                        <div class="border-t ui-border p-4 space-y-4">
                            <div class="grid grid-cols-1 gap-3">
                                <div class="rounded-xl ui-surface-bg border ui-border p-3">
                                    <p class="ui-text-muted ui-text-caption">اسم العملية</p>
                                    <p class="ui-title font-bold mt-1">{{ $operationName }}</p>
                                </div>
                                <div class="rounded-xl ui-surface-bg border ui-border p-3">
                                    <p class="ui-text-muted ui-text-caption">إجمالي الأجل</p>
                                    <p class="ui-status-info font-bold mt-1">{{ number_format((float) ($row->amount ?? 0), 2) }} ريال</p>
                                </div>
                                <div class="rounded-xl ui-surface-bg border ui-border p-3">
                                    <p class="ui-text-muted ui-text-caption">المتبقي</p>
                                    <p class="ui-status-warning font-bold mt-1">{{ number_format((float) ($row->remaining_amount ?? 0), 2) }} ريال</p>
                                </div>
                                <div class="rounded-xl ui-surface-bg border ui-border p-3">
                                    <p class="ui-text-muted ui-text-caption">التاريخ</p>
                                    <p class="ui-title font-bold mt-1">{{ $rowDate }}</p>
                                </div>
                                <div class="rounded-xl ui-surface-bg border ui-border p-3">
                                    <p class="ui-text-muted ui-text-caption">من قام بتسجيل العملية</p>
                                    <p class="ui-title font-bold mt-1">{{ $row->addedBy?->name ?? 'غير محدد' }}</p>
                                </div>
                            </div>

                            @if($linkedSale)
                                <div class="rounded-xl ui-surface-bg border ui-border p-4">
                                    <h3 class="text-sm font-bold ui-title mb-3">تفاصيل العملية</h3>
                                    <div class="space-y-2">
                                        @forelse($linkedSale->items as $item)
                                            @php
                                                $itemName = $item->historical_product_name;
                                                $unitType = match($item->unit_type ?? null) {
                                                    'piece' => 'حبة',
                                                    'meter' => 'متر',
                                                    default => '',
                                                };
                                                $quantityText = rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.');
                                            @endphp
                                            <div class="grid grid-cols-1 gap-2 rounded-lg ui-surface-muted-bg px-3 py-2 text-sm md:items-center">
                                                <span class="ui-text-muted">#{{ $loop->iteration }}</span>
                                                <span class="ui-title">{{ $itemName }} @if($unitType !== '')<span class="ui-text-muted">({{ $unitType }})</span>@endif</span>
                                                <span class="ui-text-soft">الكمية: {{ $quantityText }}</span>
                                                <span class="ui-status-info font-bold">السعر: {{ number_format((float) ($item->price ?? 0), 2) }} ريال</span>
                                            </div>
                                        @empty
                                            <p class="text-sm ui-text-muted">لا توجد منتجات محفوظة لهذه العملية.</p>
                                        @endforelse
                                    </div>

                                    @if((float) ($linkedSale->labor_total ?? 0) > 0)
                                        <div class="mt-3 grid grid-cols-1 gap-2 rounded-lg ui-surface-muted-bg px-3 py-2 text-sm md:items-center">
                                            <span class="ui-text-muted">#{{ $linkedSale->items->count() + 1 }}</span>
                                            <span class="ui-title">{{ trim((string) ($linkedSale->description ?? '')) !== '' ? $linkedSale->description : 'عمل/خدمة إضافية' }}</span>
                                            <span class="ui-status-info font-bold">السعر: {{ number_format((float) $linkedSale->labor_total, 2) }} ريال</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 gap-3">
                                    <div class="rounded-xl ui-surface-bg border ui-border p-3">
                                        <p class="ui-text-muted ui-text-caption">المدفوع كاش</p>
                                        <p class="ui-status-success font-bold mt-1">{{ number_format((float) ($linkedSale->cash_amount ?? 0), 2) }} ريال</p>
                                    </div>
                                    <div class="rounded-xl ui-surface-bg border ui-border p-3">
                                        <p class="ui-text-muted ui-text-caption">المدفوع شبكة</p>
                                        <p class="ui-status-info font-bold mt-1">{{ number_format((float) ($linkedSale->card_amount ?? 0), 2) }} ريال</p>
                                    </div>
                                    @if($partialCollectionTotal > 0)
                                        <div class="rounded-xl ui-surface-bg border ui-status-success-border p-3">
                                            <p class="ui-status-success ui-text-caption">تحصيل جزئي</p>
                                            <p class="ui-status-success font-bold mt-1">{{ number_format((float) $partialCollectionTotal, 2) }} ريال</p>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="rounded-xl border ui-border ui-surface-bg p-3 text-sm ui-text-muted">لا توجد عملية مبيعات مرتبطة لعرض المنتجات والعمل.</p>
                            @endif

                            <div class="rounded-xl ui-surface-bg border ui-border p-4">
                                <h3 class="text-sm font-bold ui-title mb-3">عمليات التحصيل</h3>
                                @if($payments->isEmpty())
                                    <p class="text-sm ui-text-muted">لا توجد تحصيلات آجل لهذه العملية.</p>
                                @else
                                    <div class="space-y-2">
                                        @foreach($payments as $paymentIndex => $payment)
                                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg ui-surface-muted-bg px-3 py-2 text-sm">
                                                <span class="ui-title">
                                                    #{{ $paymentIndex + 1 }}
                                                    {{ ($payment['description'] ?? '') === 'تحصيل كامل' ? 'تحصيل كامل' : 'تحصيل جزئي' }}
                                                </span>
                                                <span class="ui-status-success">{{ number_format((float) ($payment['amount'] ?? 0), 2) }} ريال</span>
                                                <span class="ui-text-soft">{{ isset($payment['date']) ? \Carbon\Carbon::parse($payment['date'])->format('Y-m-d') : '-' }}</span>
                                                <span class="ui-text-muted">المحصل: {{ $payment['added_by_name'] ?? 'غير محدد' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            @if($linkedSaleId && !empty($row->store_id))
                                <div class="flex flex-wrap gap-2 justify-end">
                                    {{-- إصلاح مطبق: تحذير الانتقال لتعديل عملية الشفت المغلق يمر عبر عقد التنقل المركزي. --}}
                                    <a href="{{ $linkedSaleEditUrl }}"
                                       @if($isLinkedShiftClosed)
                                           data-ui-confirm-navigation="{{ $closedShiftWarning }} هل تريد الانتقال للتعديل؟"
                                           data-ui-confirm-title="تأكيد الانتقال للتعديل"
                                       @endif
                                       class="ui-btn ui-btn-secondary px-4 py-2 text-sm">
                                        تعديل العملية
                                    </a>
                                    @if($isOutsideCurrentMonth)
                                        <p class="w-full ui-text-caption ui-status-warning text-left md:text-right">هذه العملية من شهر سابق؛ التعديل سيفتح العملية لتغيير الموظف فقط، أما الحذف فيبقى ظاهرًا مع تنبيه حذف الأجل المرتبط.</p>
                                    @endif
                                    <form method="POST"
                                          action="{{ route('user.stores.daily.destroy', [$row->store_id, $linkedSaleId, 'return_to' => request()->fullUrl()]) }}"
                                          data-confirm-delete="{{ $isLinkedShiftClosed ? $closedShiftWarning . ' ' : '' }}سيتم حذف عملية البيع والأجل المرتبط بها من سجل الموظف ومن كل مكان، وسيتم تسجيل بيانات الحذف والتحصيلات، مع استرجاع المنتجات للمخزون. هل تريد المتابعة؟">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="confirm_credit_delete" value="1">
                                        <button type="submit" class="ui-btn ui-btn-danger px-4 py-2 text-sm">
                                            حذف العملية
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="rounded-xl border ui-status-warning-border ui-status-warning-bg p-3 text-sm ui-status-warning">
                                    لا تظهر أزرار التعديل/الحذف لأن سجل الأجل غير مرتبط برقم عملية مبيعات يومية محفوظ. يمكن متابعة السجل من التحصيلات، أما تعديل/حذف عملية البيع يحتاج رقم العملية المرتبطة.
                                </div>
                            @endif

                        </div>
                    </details>
                @empty
                    <div class="px-4 py-10 text-center ui-text-muted">
                        لا توجد عمليات أجل في الشهر المحدد
                    </div>
                @endforelse
            </div>
        @else
        <div class="ui-help-modal-body overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="ui-table-head ui-text-soft">
                    <tr>
                        @foreach($columns as $label)
                            <th class="px-4 py-3 font-semibold whitespace-nowrap">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="ui-divide-border">
                    @forelse($rows as $row)
                        <tr class="ui-hover-surface ui-title">
                            @foreach($columns as $key => $label)
                                <td class="px-4 py-3 align-top whitespace-nowrap">
                                    @switch($key)
                                        @case('_serial')
                                            #{{ $loop->parent->iteration }}
                                            @break

                                        @case('amount')
                                        @case('remaining_amount')
                                            {{ number_format((float) ($row->{$key} ?? 0), 2) }} ريال
                                            @break

                                        @case('signed_amount')
                                            <span class="{{ (float) ($row->amount ?? 0) < 0 ? 'ui-status-success' : 'ui-status-danger' }}">
                                                {{ number_format((float) ($row->amount ?? 0), 2) }} ريال
                                            </span>
                                            @break

                                        @case('date')
                                            {{ optional($row->date)->format('Y-m-d') ?? $row->date ?? optional($row->created_at)->format('Y-m-d') ?? '-' }}
                                            @break

                                        @case('accounting_date')
                                            {{ $row->accounting_date_display ?? '-' }}
                                            @break

                                        @case('added_by')
                                            {{ $row->executed_by_name ?? $row->addedBy?->name ?? 'غير محدد' }}
                                            @break


                                        @case('credit_sale_actions')
                                            @php($linkedSaleId = method_exists($row, 'resolveLinkedSaleId') ? $row->resolveLinkedSaleId() : null)
                                            @if($linkedSaleId && !empty($row->store_id))
                                                <div class="flex flex-wrap gap-2 whitespace-normal">
                                                    <a href="{{ $linkedSaleEditUrl }}"
                                                       class="ui-btn ui-btn-secondary px-3 py-2 ui-text-caption">
                                                        تعديل
                                                    </a>
                                                    @if($isOutsideCurrentMonth)
                                                        <span class="w-full ui-text-caption ui-status-warning">عملية من شهر سابق: التعديل للموظف فقط، والحذف ظاهر مع تنبيه حذف الأجل المرتبط.</span>
                                                    @endif
                                                    <form method="POST"
                                                          action="{{ route('user.stores.daily.destroy', [$row->store_id, $linkedSaleId, 'return_to' => request()->fullUrl()]) }}"
                                                          data-confirm-delete="سيتم حذف عملية البيع والأجل المرتبط بها من سجل الموظف ومن كل مكان، وسيتم تسجيل بيانات الحذف والتحصيلات، مع استرجاع المنتجات للمخزون. هل تريد المتابعة؟">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="confirm_credit_delete" value="1">
                                                        <button type="submit" class="ui-btn ui-btn-danger px-3 py-2 ui-text-caption">
                                                            حذف
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="ui-text-muted">غير مرتبطة بعملية مبيعات</span>
                                            @endif
                                            @break

                                        @case('credit_partial_status')
                                            @php($payments = collect($row->collection_payments ?? []))
                                            @php($partialTotal = $payments->sum(fn ($payment) => (float) ($payment['amount'] ?? 0)))
                                            @if($partialTotal > 0 && (float) ($row->remaining_amount ?? 0) > 0)
                                                <span class="inline-flex items-center rounded-full border ui-status-info-border ui-status-info-bg px-2 py-1 ui-text-caption font-bold ui-status-info">
                                                    نعم - {{ number_format($partialTotal, 2) }} ريال
                                                </span>
                                            @elseif($partialTotal > 0)
                                                <span class="inline-flex items-center rounded-full border ui-status-success-border ui-status-success-bg px-2 py-1 ui-text-caption font-bold ui-status-success">
                                                    تم السداد
                                                </span>
                                            @else
                                                <span class="ui-text-muted">لا</span>
                                            @endif
                                            @break

                                        @case('collection_payments')
                                            @php($payments = collect($row->collection_payments ?? []))
                                            @if($payments->isEmpty())
                                                <span class="ui-text-muted">لا توجد تحصيلات</span>
                                            @else
                                                <div class="space-y-1 whitespace-normal">
                                                    @foreach($payments as $payment)
                                                        <div class="ui-text-caption ui-text-soft">
                                                            {{ $payment['payment_method_label'] ?? 'كاش' }}
                                                            — {{ number_format((float) ($payment['amount'] ?? 0), 2) }} ريال
                                                            — {{ $payment['added_by_name'] ?? 'غير محدد' }}
                                                            <span class="ui-text-muted">({{ number_format((float) ($payment['cash_amount'] ?? 0), 2) }} كاش / {{ number_format((float) ($payment['card_amount'] ?? 0), 2) }} شبكة)</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @break

                                        @case('description')
                                            <span class="whitespace-normal">{{ filled($row->description ?? null) ? $row->description : 'لا توجد ملاحظات' }}</span>
                                            @break

                                        @default
                                            <span class="whitespace-normal">{{ $row->{$key} ?: '-' }}</span>
                                    @endswitch
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-4 py-10 text-center ui-text-muted">
                                لا توجد بيانات
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
