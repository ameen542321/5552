<div
    x-data="tintSaleModal()"
    data-tint-sale-config="{{ json_encode(['products' => $tintProducts ?? []], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
    @open-tint-sale-modal.window="openModal()"
    @keydown.escape.window="if (open) closeModal()"
    x-cloak
>
    <div x-show="open" x-transition.opacity.duration.150ms class="ui-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="quick-sale-tint-modal-title">
        <div class="ui-modal-panel ui-modal-panel-wide">
            <header class="ui-modal-header">
                <div class="min-w-0">
                    <h2 id="quick-sale-tint-modal-title" class="text-base font-black ui-title sm:text-lg">بيع التضليل</h2>
                    <p class="mt-0.5 ui-text-caption ui-text-muted sm:ui-text-caption">اختر العمل ثم النوع والحجم والدرجة بأزرار سريعة.</p>
                </div>
                <button type="button" @click="closeModal()" class="ui-btn ui-btn-danger shrink-0 px-3 py-2 ui-text-caption sm:px-4 sm:text-sm">إغلاق</button>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-5" x-ref="modalScroller">
                <div x-show="!products.length" class="rounded-2xl border ui-border ui-status-warning-bg p-4 text-sm font-bold ui-status-warning">لا توجد منتجات تضليل متوفرة للبيع.</div>

                <div x-show="products.length" class="space-y-4">
                    <section class="rounded-2xl border ui-border ui-surface-muted-bg p-3 sm:p-4">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h3 class="text-sm font-black ui-title">نوع العمل</h3>
                            <span class="group relative flex h-5 w-5 cursor-help items-center justify-center rounded-full border ui-border ui-text-caption ui-text-muted" tabindex="0">؟
                                <span class="ui-tooltip-popover pointer-events-none leading-5">«كامل» يلغي الأعمال الجزئية. ويمكن الجمع بين أمامي وخلفي ودريشة، وسيتم نقلك تلقائيًا إلى حقول العمل الجديد.</span>
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button type="button" @click="selectFullWork()" :class="fullMode ? activeButton : idleButton" class="rounded-xl border px-3 py-3 text-sm font-black transition">كامل</button>
                            <button type="button" @click="toggleWork('front')" :class="isWorkSelected('front') ? activeButton : idleButton" class="rounded-xl border px-3 py-3 text-sm font-black transition">أمامي</button>
                            <button type="button" @click="toggleWork('rear')" :class="isWorkSelected('rear') ? activeButton : idleButton" class="rounded-xl border px-3 py-3 text-sm font-black transition">خلفي</button>
                            <button type="button" @click="toggleWork('window')" :class="isWorkSelected('window') ? activeButton : idleButton" class="rounded-xl border px-3 py-3 text-sm font-black transition">دريشة</button>
                        </div>
                        <div x-show="isWorkSelected('window')" class="mt-3 flex items-center gap-2 rounded-xl border ui-border ui-status-info-bg p-2">
                            <span class="ml-auto ui-text-caption font-black ui-status-info">عدد الدرايش</span>
                            <template x-for="count in [1, 2, 3, 4]" :key="count">
                                <button type="button" @click="windowCount = count; syncPrice()" :class="windowCount === count ? 'ui-status-info-bg ui-title ui-border shadow-lg ' : 'border ui-border ui-surface-muted-bg ui-status-info'" class="h-10 w-10 rounded-xl text-sm font-black transition" x-text="count"></button>
                            </template>
                        </div>
                    </section>

                    <section x-show="fullMode" class="space-y-3">
                        <div class="grid grid-cols-3 gap-2 rounded-2xl border ui-border ui-surface-muted-bg p-2">
                            <template x-for="component in fullComponents" :key="'tab-' + component.id">
                                <button
                                    type="button"
                                    @click="activeWork = component.id"
                                    :class="activeWork === component.id ? activeButton : idleButton"
                                    class="rounded-xl border px-2 py-2.5 ui-text-caption font-black transition sm:ui-text-caption"
                                >
                                    <span x-text="component.shortLabel"></span>
                                    <span x-show="isFullComponentComplete(component)" class="mr-1 ui-status-success">✓</span>
                                </button>
                            </template>
                        </div>
                        <template x-for="component in fullComponents" :key="component.id">
                            <div x-show="activeWork === component.id" :id="'tint-full-' + component.id" class="rounded-2xl border ui-border ui-surface-muted-bg p-3 sm:p-4">
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <div>
                                        <h3 class="text-sm font-black ui-title" x-text="component.label"></h3>
                                        <p class="mt-0.5 ui-text-caption ui-text-muted" x-text="component.hint"></p>
                                    </div>
                                    <span class="rounded-full ui-status-info-bg px-2 py-1 ui-text-caption font-bold ui-status-info" x-text="component.quantity > 1 ? ('× ' + component.quantity) : 'قطعة واحدة'"></span>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <span class="mb-1.5 block ui-text-caption font-bold ui-text-muted">نوع التضليل</span>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="type in availableTypesForWork(component.work)" :key="type.id">
                                                <button type="button" @click="selectType(fullSelections[component.id], type.id)" :class="fullSelections[component.id].type === type.id ? activeButton : idleButton" class="rounded-lg border px-3 py-2 ui-text-caption font-black" x-text="type.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div x-show="fullSelections[component.id].type">
                                        <span class="mb-1.5 block ui-text-caption font-bold ui-text-muted">الحجم</span>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="size in sizesFor(component.work, fullSelections[component.id].type)" :key="size.id">
                                                <button type="button" @click="selectSize(fullSelections[component.id], size.id)" :class="fullSelections[component.id].size === size.id ? activeButton : idleButton" class="rounded-lg border px-3 py-2 ui-text-caption font-black" x-text="size.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div x-show="fullSelections[component.id].size">
                                        <span class="mb-1.5 block ui-text-caption font-bold ui-text-muted">الدرجة</span>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="grade in gradesFor(component.work, fullSelections[component.id].type, fullSelections[component.id].size)" :key="grade">
                                                <button type="button" @click="selectGrade(fullSelections[component.id], grade); advanceFullComponent(component.id)" :class="fullSelections[component.id].grade === grade ? activeButton : idleButton" class="rounded-lg border px-3 py-2 ui-text-caption font-black" x-text="grade"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <p x-show="selectionStockMessage(component.work, fullSelections[component.id], component.quantity)" :class="selectionHasStock(component.work, fullSelections[component.id], component.quantity) ? 'ui-status-success' : 'ui-status-danger'" class="rounded-lg border border-current/20 ui-surface-muted-bg px-3 py-2 ui-text-caption font-bold" x-text="selectionStockMessage(component.work, fullSelections[component.id], component.quantity)"></p>
                                </div>
                            </div>
                        </template>
                    </section>

                    <section x-show="!fullMode && selectedWorks.length" class="space-y-3">
                        <template x-for="work in selectedWorks" :key="work">
                            <div x-show="activeWork === work" :id="'tint-work-' + work" class="rounded-2xl border ui-border ui-surface-muted-bg p-3 sm:p-4">
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-black ui-title" x-text="workLabel(work) + (work === 'window' ? ' × ' + windowCount : '')"></h3>
                                    <button type="button" @click="removeWork(work)" class="ui-btn ui-btn-danger px-2 py-1 ui-text-caption">إلغاء</button>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <span class="mb-1.5 block ui-text-caption font-bold ui-text-muted">نوع التضليل</span>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="type in availableTypesForWork(work)" :key="type.id">
                                                <button type="button" @click="selectType(workSelections[work], type.id)" :class="workSelections[work]?.type === type.id ? activeButton : idleButton" class="rounded-lg border px-3 py-2 ui-text-caption font-black" x-text="type.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div x-show="workSelections[work]?.type">
                                        <span class="mb-1.5 block ui-text-caption font-bold ui-text-muted">الحجم</span>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="size in sizesFor(work, workSelections[work]?.type)" :key="size.id">
                                                <button type="button" @click="selectSize(workSelections[work], size.id)" :class="workSelections[work]?.size === size.id ? activeButton : idleButton" class="rounded-lg border px-3 py-2 ui-text-caption font-black" x-text="size.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div x-show="workSelections[work]?.size">
                                        <span class="mb-1.5 block ui-text-caption font-bold ui-text-muted">الدرجة</span>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="grade in gradesFor(work, workSelections[work]?.type, workSelections[work]?.size)" :key="grade">
                                                <button type="button" @click="selectGrade(workSelections[work], grade); advanceSelectedWork(work)" :class="workSelections[work]?.grade === grade ? activeButton : idleButton" class="rounded-lg border px-3 py-2 ui-text-caption font-black" x-text="grade"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <p x-show="selectionStockMessage(work, workSelections[work], work === 'window' ? windowCount : 1)" :class="selectionHasStock(work, workSelections[work], work === 'window' ? windowCount : 1) ? 'ui-status-success' : 'ui-status-danger'" class="rounded-lg border border-current/20 ui-surface-muted-bg px-3 py-2 ui-text-caption font-bold" x-text="selectionStockMessage(work, workSelections[work], work === 'window' ? windowCount : 1)"></p>
                                </div>
                            </div>
                        </template>
                    </section>

                    <section class="rounded-2xl border ui-border ui-surface-muted-bg p-3 sm:p-4">
                        <button type="button" @click="toggleCustomPanel()" class="flex w-full items-center justify-between gap-3 text-right">
                            <span><strong class="block text-sm ui-title">إضافة مخصصة</strong><small class="ui-text-caption ui-text-muted">اختر الرول ثم استخدم استهلاك أحد خياراته أو أدخل أمتارًا يدويًا.</small></span>
                            <span class="text-lg font-black ui-status-info" x-text="customOpen ? '−' : '+'"></span>
                        </button>
                        <div x-show="customOpen" class="mt-3 space-y-3">
                            <template x-for="row in customRows" :key="row.id">
                                <div class="space-y-3 rounded-xl border ui-border ui-surface-muted-bg p-3">
                                    <div>
                                        <span class="mb-1.5 block ui-text-caption font-bold ui-text-muted">منتج الرول</span>
                                        <div class="flex max-h-28 flex-wrap gap-2 overflow-y-auto">
                                            <template x-for="product in products" :key="product.id">
                                                <button type="button" @click="selectCustomProduct(row, product.id)" :class="String(row.productId) === product.id ? activeButton : idleButton" class="rounded-lg border px-2.5 py-2 ui-text-caption font-black" x-text="product.name"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div x-show="row.productId">
                                        <span class="mb-1.5 block ui-text-caption font-bold ui-text-muted">أمتار جاهزة من خيارات التجزئة</span>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="fraction in customProductFractions(row)" :key="fraction.id">
                                                <button type="button" @click="selectCustomFraction(row, fraction)" :class="String(row.fractionId) === fraction.id ? activeButton : idleButton" class="rounded-lg border px-2.5 py-2 ui-text-caption font-black">
                                                    <span x-text="fraction.label"></span><span class="mr-1 opacity-75" x-text="fraction.meters + 'م'"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                        <input type="text" x-model="row.name" placeholder="وصف التسجيل المخصص" class="rounded-lg border ui-border ui-surface-muted-bg px-3 py-2.5 ui-text-caption font-bold ui-title">
                                        <input type="number" min="0.01" step="0.01" x-model.number="row.meters" @input="row.fractionId = ''; syncPrice()" placeholder="الأمتار أو اختر قيمة جاهزة" class="rounded-lg border ui-border ui-surface-muted-bg px-3 py-2.5 ui-text-caption font-bold ui-title">
                                        <div class="flex gap-2">
                                            <input type="number" min="0.01" step="0.01" x-model.number="row.price" @input="syncPrice()" placeholder="سعر البيع" class="min-w-0 flex-1 rounded-lg border ui-border ui-surface-muted-bg px-3 py-2.5 ui-text-caption font-bold ui-title">
                                            <button type="button" @click="removeCustomRow(row.id)" class="ui-btn ui-btn-danger px-3 ui-text-caption">حذف</button>
                                        </div>
                                    </div>
                                    <p x-show="customStockMessage(row)" :class="customHasStock(row) ? 'ui-status-success' : 'ui-status-danger'" class="ui-text-caption font-bold" x-text="customStockMessage(row)"></p>
                                </div>
                            </template>
                            <button type="button" @click="addCustomRow()" class="rounded-xl border border-dashed ui-border px-3 py-2 ui-text-caption font-black ui-status-info">+ إضافة سطر مخصص آخر</button>
                        </div>
                    </section>

                    <section class="rounded-2xl border ui-border ui-surface-muted-bg p-3 sm:p-4">
                        <div class="mb-3 flex items-center justify-between gap-2"><h3 class="text-sm font-black ui-title">ملخص العملية</h3><span class="ui-text-caption font-bold ui-text-muted" x-text="resolvedParts.length ? resolvedParts.length + ' اختيار' : 'لم يكتمل أي اختيار'"></span></div>
                        <div x-show="stockErrors(resolvedParts).length" class="mb-3 rounded-xl border ui-border ui-status-danger-bg p-3 ui-text-caption font-bold ui-status-danger">
                            <template x-for="message in stockErrors(resolvedParts)" :key="message"><p x-text="message"></p></template>
                        </div>
                        <div class="space-y-2">
                            <template x-for="part in resolvedParts" :key="part.key">
                                <div class="flex items-start justify-between gap-3 rounded-xl border ui-border ui-surface-muted-bg p-3">
                                    <div class="min-w-0"><strong class="block ui-text-caption ui-title" x-text="part.label"></strong><span class="mt-1 block ui-text-caption ui-text-muted" x-text="part.product.name + ' — ' + partDisplayRegistration(part)"></span></div>
                                    <span class="shrink-0 ui-text-caption font-black ui-status-success" x-text="money(part.linePrice)"></span>
                                </div>
                            </template>
                            <div x-show="!resolvedParts.length" class="rounded-xl border border-dashed ui-border px-3 py-6 text-center ui-text-caption ui-text-muted">حدد العمل والمنتجات لإظهار الملخص.</div>
                        </div>
                        <label class="mt-4 block space-y-1"><span class="ui-text-caption font-bold ui-text-muted">سعر العملية النهائي</span><input type="number" min="0.01" step="0.01" x-model.number="finalPrice" class="w-full rounded-xl border ui-border ui-surface-muted-bg px-4 py-3 text-center text-xl font-black ui-status-success outline-none "></label>
                    </section>
                </div>
            </div>

            <footer class="flex shrink-0 gap-2 border-t ui-border ui-surface-muted-bg p-3 sm:justify-end sm:px-5">
                <button type="button" @click="resetBuilder()" class="ui-btn ui-btn-danger flex-1 px-4 py-3 ui-text-caption sm:flex-none">بدء جديد</button>
                {{-- يبقى الزر قابلاً للضغط دائمًا؛ دالة الإضافة تعرض سبب المنع بوضوح بدل تعطيله بصمت. --}}
                <button type="button" @click="addToQuickSaleCart()" class="ui-btn ui-btn-success flex-[2] px-4 py-3 text-sm sm:flex-none sm:min-w-48">إضافة إلى السلة</button>
            </footer>
        </div>
    </div>
</div>
