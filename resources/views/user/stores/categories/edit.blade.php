@extends('dashboard.app')

@section('title', ($is_main_category ? 'تعديل النشاط – متجر ' : 'تعديل القسم – متجر ') . $store->name)

@section('content')

<div class="max-w-3xl mx-auto px-4 py-6 sm:py-10" dir="rtl" data-category-system>

    {{-- الهيدر --}}
    <div class="mb-6 grid grid-cols-1 gap-4 md:mb-10 md:grid-cols-[auto_1fr_5rem] md:items-center">
        <a href="{{ route('user.stores.categories.index', $store->id) }}"
           class="inline-flex w-fit items-center gap-2 px-4 py-2 rounded-lg ui-card ui-text-soft ui-hover-info-bg ui-title transition shadow-sm">
            <i class="fa-solid fa-arrow-right text-sm"></i>
            <span class="text-sm font-medium">رجوع</span>
        </a>

        <h1 class="text-center text-2xl font-bold ui-title">
            {{ $is_main_category ? 'تعديل النشاط' : 'تعديل القسم' }}
        </h1>
        <div class="hidden w-20 md:block" aria-hidden="true"></div>
    </div>

    {{-- النموذج --}}
    <div class="ui-card rounded-xl p-5 shadow-lg sm:p-8">
        <form action="{{ route('user.stores.categories.update', [$store->id, $category->id]) }}" method="POST" id="category_edit_form" data-current-store="{{ $store->name }}" data-item-label="{{ $is_main_category ? 'النشاط' : 'القسم' }}">
            @csrf
            @method('PUT')

<input type="hidden" name="is_main_category" value="{{ $category->is_main_category ? 1 : 0 }}">
            {{-- الاسم --}}
            <div class="mb-6 text-right">
                <label class="block ui-text-soft mb-2 font-medium">
                    {{ $is_main_category ? 'اسم النشاط' : 'اسم القسم' }}
                </label>
                <input type="text" name="name" id="category_name" value="{{ old('name', $category->name) }}"
                       class="w-full ui-card ui-title rounded-lg px-4 py-2   transition outline-none">

                @unless($is_main_category)
                    @php
                        $currentPreset = old('category_name_preset', $category->name === 'تضليل' ? 'tint' : ($category->name === 'تنجيد وتلابيس' ? 'upholstery' : ''));
                    @endphp
                    <input type="hidden" name="category_name_preset" id="category_name_preset" value="{{ $currentPreset }}">
                    <div class="mt-4 p-4 ui-status-info-bg border ui-border rounded-xl">
                        <p class="text-sm font-bold ui-status-info mb-2">أسماء أقسام معتمدة للنظام</p>
                        <p class="ui-text-caption ui-text-muted mb-3">استخدم أحد الزرين لتثبيت اسم القسم دون أخطاء إملائية. ويمكنك كتابة أي اسم آخر يدويًا للأقسام الأخرى.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button type="button" data-category-preset="tint" data-category-name="تضليل"
                                    class="border ui-border ui-status-info-bg ui-hover-info-bg ui-status-info rounded-lg px-4 py-3 text-sm font-bold transition">
                                <i class="fa-solid fa-sun ml-1"></i> تضليل
                            </button>
                            <button type="button" data-category-preset="upholstery" data-category-name="تنجيد وتلابيس"
                                    class="border ui-border ui-status-warning-bg ui-hover-info-bg ui-status-warning rounded-lg px-4 py-3 text-sm font-bold transition">
                                <i class="fa-solid fa-couch ml-1"></i> تنجيد وتلابيس
                            </button>
                        </div>
                        <p id="category_preset_notice" class="hidden mt-3 ui-text-caption ui-status-success"></p>
                        @if($category->products()->exists())
                            <p class="mt-3 ui-text-caption ui-status-warning"><i class="fa-solid fa-triangle-exclamation ml-1"></i> تغيير اسم هذا القسم يؤثر على تصنيف المنتجات المرتبطة به في شاشات البيع المتخصصة.</p>
                        @endif
                    </div>
                @endunless
                @error('name') <p class="ui-status-danger text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- الوصف --}}
            <div class="mb-6 text-right">
                <label class="block ui-text-soft mb-2 font-medium">الوصف</label>
                <textarea name="description" rows="3"
                          class="w-full ui-card ui-title rounded-lg px-4 py-2  transition outline-none">{{ old('description', $category->description) }}</textarea>
            </div>

            {{-- الحالة --}}
            <div class="mb-6 text-right">
                <label class="block ui-text-soft mb-2 font-medium">الحالة</label>
                <select name="status" class="w-full ui-card ui-title rounded-lg px-4 py-2  outline-none transition">
                    <option value="active" {{ old('status', $category->status) == 'active' ? 'selected' : '' }}>مفعل</option>
                    <option value="inactive" {{ old('status', $category->status) == 'inactive' ? 'selected' : '' }}>غير مفعل</option>
                </select>
            </div>

            <hr class="ui-border my-8">

            {{-- نقل القسم إلى متجر آخر عند اختيار المالك لذلك. --}}
            <div class="ui-status-info-bg border ui-border p-6 rounded-xl text-right">
                <h3 class="ui-brand-text font-bold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-truck-fast"></i> نقل القسم لمتجر آخر (اختياري)
                </h3>

                <p class="ui-text-caption ui-status-info mb-4 leading-6">
                    إذا تركت المتجر كما هو فسيتم حفظ البيانات فقط. عند اختيار متجر آخر سيظهر تأكيد قبل نقل {{ $is_main_category ? 'النشاط' : 'القسم' }}.
                </p>

                <div class="mb-4">
                    <label class="block ui-text-muted text-sm mb-2 font-medium">اختر المتجر الجديد في حال رغبت بنقل القسم</label>
                    <select name="target_store_id" id="target_store_id" class="w-full ui-card ui-title rounded-lg px-4 py-2  outline-none transition">
                        <option value="">-- ابقائه في المتجر الحالي --</option>
                        @foreach(App\Models\Store::where('user_id', auth()->id())->where('id', '!=', $store->id)->get() as $otherStore)
                            <option value="{{ $otherStore->id }}" {{ old('target_store_id') == $otherStore->id ? 'selected' : '' }}>{{ $otherStore->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" name="move_products" id="move_products" value="1" checked class="w-4 h-4 rounded ui-border ui-surface-muted-bg ui-brand-text">
                    <label for="move_products" class="ui-text-muted ui-text-caption">نقل كافة المنتجات المرتبطة بهذا القسم للمتجر الجديد</label>
                </div>
            </div>

            {{-- الأزرار --}}
            <div class="flex items-center justify-between mt-10">
                <button type="submit" class="flex items-center justify-center ui-btn ui-btn-primary ui-title px-8 py-2 rounded-lg transition shadow-md font-bold">
                    <i class="fa-solid fa-floppy-disk ml-2"></i> حفظ التغييرات
                </button>
            </div>

        </form>
    </div>
</div>

@endsection
