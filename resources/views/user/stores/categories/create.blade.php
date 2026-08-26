@extends('dashboard.app')

@section('title', ($is_main_category ? 'إضافة نشاط جديد – متجر ' : 'إضافة قسم جديد – متجر ') . $store->name)

@section('content')

<div class="max-w-3xl mx-auto px-4 py-6 sm:py-10" data-category-system>

   <div class="mb-6 grid grid-cols-1 gap-4 md:mb-10 md:grid-cols-[auto_1fr_5rem] md:items-center">

    {{-- زر الرجوع --}}
    <a href="{{ route('user.stores.categories.index', $store->id) }}"
       class="inline-flex w-fit items-center gap-2 px-4 py-2 rounded-lg ui-card ui-text-soft ui-hover-info-bg ui-title transition shadow-sm">
        <i class="fa-solid fa-arrow-right text-sm"></i>
        <span class="text-sm font-medium">رجوع</span>
    </a>

    {{-- عنوان الصفحة --}}
    <h1 class="text-center text-2xl font-bold ui-title">
        {{ $is_main_category ? 'إضافة نشاط جديد' : 'إضافة قسم جديد' }}
    </h1>

    <div class="hidden w-20 md:block" aria-hidden="true"></div>
</div>

    <div class="ui-card rounded-xl p-5 shadow-lg sm:p-8">

        <form action="{{ route('user.stores.categories.store', $store->id) }}" method="POST">
            @csrf

            {{-- نوع القسم (نشاط / قسم عادي) --}}
            <input type="hidden" name="is_main_category" value="{{ $is_main_category }}">

            {{-- الاسم --}}
            <div class="mb-6">
                <label class="block ui-text-soft mb-2 font-medium">
                    {{ $is_main_category ? 'اسم النشاط' : 'اسم القسم' }}
                </label>

                <input type="text" name="name" id="category_name"
                       value="{{ old('name') }}"
                       class="w-full ui-card ui-title rounded-lg px-4 py-2   transition">

                @unless($is_main_category)
                    <input type="hidden" name="category_name_preset" id="category_name_preset" value="{{ old('category_name_preset') }}">
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
                    </div>
                @endunless

                @error('name')
                    <p class="ui-status-danger text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- الوصف --}}
            <div class="mb-6">
                <label class="block ui-text-soft mb-2 font-medium">الوصف</label>
                <textarea name="description" rows="4"
                          class="w-full ui-card ui-title rounded-lg px-4 py-2   transition">{{ old('description') }}</textarea>
            </div>

            {{-- الحالة --}}
            <div class="mb-6">
                <label class="block ui-text-soft mb-2 font-medium">الحالة</label>
                <select name="status"
                        class="w-full ui-card ui-title rounded-lg px-4 py-2   transition">
                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>مفعل</option>
                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>غير مفعل</option>
                </select>
            </div>

            {{-- الأزرار --}}
            <div class="flex items-center justify-between mt-10">

                {{-- زر الإضافة --}}
                <button type="submit"
                        class="flex items-center ui-btn ui-btn-primary ui-title px-6 py-2 rounded-lg transition shadow-md">
                    <i class="fa-solid fa-plus ml-2"></i>
                    {{ $is_main_category ? 'إضافة النشاط' : 'إضافة القسم' }}
                </button>

            </div>

        </form>

    </div>

</div>

@endsection
