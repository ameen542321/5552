<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ArchivedItem;
use App\Services\AdministrativeArchiveService;
use App\Services\SupportSessionService;

class CategoryController extends Controller
{
    /**
     * عرض الأقسام الخاصة بمتجر معين
     */
    public function index(Store $store)
    {
        $categories = Category::where('store_id', $store->id)->get();
        $trashCount = Category::onlyTrashed()
            ->where('store_id', $store->id)
            ->whereNotIn('id', app(AdministrativeArchiveService::class)->archivedIds(Category::class))
            ->count();

        return view('user.stores.categories.index', compact('store', 'trashCount', 'categories'));
    }

    /**
     * صفحة إضافة قسم أو نشاط
     */
    public function create(Store $store, Request $request)
    {
        $is_main_category = $request->get('is_main_category', 0);
        return view('user.stores.categories.create', compact('store', 'is_main_category'));
    }

    /**
     * حفظ قسم جديد أو نشاط جديد
     */
    public function store(Request $request, Store $store)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'status'           => 'required|in:active,inactive',
            'is_main_category'    => 'required|boolean',
            'category_name_preset' => 'nullable|in:tint,upholstery',
        ]);

        Category::create([
            // الاسم المعتمد يُبنى في الخادم حتى لا يمكن تغيير «تضليل» أو «تنجيد وتلابيس» من المتصفح.
            'name'             => $this->resolveCategoryName($request->name, $request->category_name_preset, $request->boolean('is_main_category')),
            'description'      => $request->description,
            'status'           => $request->status,
            'store_id'         => $store->id,
            'user_id'          => auth()->id(),
            'is_main_category' => $request->is_main_category,
        ]);

        return redirect()
            ->route('user.stores.categories.index', $store->id)
            ->with('success', 'تم إضافة القسم بنجاح');
    }

    /**
     * صفحة تعديل قسم أو نشاط (تشمل خيار النقل)
     */
    public function edit(Store $store, Category $category)
    {
        if ($category->store_id != $store->id) {
            abort(403);
        }

        $is_main_category = $category->is_main_category;

        return view('user.stores.categories.edit', compact('store', 'category', 'is_main_category'));
    }

    /**
     * تحديث القسم أو النشاط + منطق النقل لمتجر آخر
     */
    public function update(Request $request, Store $store, Category $category)
    {
        if ($category->store_id != $store->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_name_preset' => 'nullable|in:tint,upholstery',
            'status' => 'required|in:active,inactive',
            'target_store_id' => 'nullable|integer',
            'move_products' => 'nullable|boolean',
        ]);

        $targetStoreId = null;
        if (!empty($validated['target_store_id'])) {
            $targetStoreId = Store::query()
                ->where('user_id', auth()->id())
                ->whereKey((int) $validated['target_store_id'])
                ->whereKeyNot($store->id)
                ->value('id');
        }

        DB::transaction(function () use ($request, $category, $validated, $targetStoreId) {
            $category->update([
                // عند اختيار أحد الزرين، نحفظ الاسم المعتمد مهما كانت قيمة حقل الاسم المرسلة.
                'name' => $this->resolveCategoryName($validated['name'], $validated['category_name_preset'] ?? null, (bool) $category->is_main_category),
                'description' => $request->description,
                'status' => $validated['status'],
                'is_main_category' => $request->is_main_category ?? $category->is_main_category,
            ]);

            if ($targetStoreId) {
                // عند نقل القسم إلى متجر آخر قد يوجد قسم بنفس الرابط المختصر داخل المتجر الهدف.
                // لذلك نعيد توليد slug فريد قبل تغيير store_id لتفادي قيد categories_store_slug_unique.
                $category->forceFill([
                    'store_id' => $targetStoreId,
                    'slug' => Category::uniqueSlugForStore($category->name, $targetStoreId, $category->id),
                ])->save();

                if ($request->boolean('move_products')) {
                    DB::table('products')
                        ->where('category_id', $category->id)
                        ->update(['store_id' => $targetStoreId]);
                }
            }
        });

        if ($targetStoreId) {
            $transferMessage = $request->boolean('move_products')
                ? 'تم نقل القسم والمنتجات إلى المتجر الجديد بنجاح'
                : 'تم نقل القسم إلى المتجر الجديد بنجاح';

            return redirect()
                ->route('user.stores.categories.index', $targetStoreId)
                ->with('success', $transferMessage);
        }

        return redirect()
            ->route('user.stores.categories.index', $store->id)
            ->with('success', 'تم تحديث بيانات القسم بنجاح');
    }

    /**
     * يحافظ على الأسماء التي تعتمد عليها الشاشات المتخصصة، مع السماح بأسماء يدوية لبقية الأقسام.
     */
    private function resolveCategoryName(string $name, ?string $preset, bool $isMainCategory): string
    {
        if ($isMainCategory) {
            return trim($name);
        }

        return match ($preset) {
            'tint' => 'تضليل',
            'upholstery' => 'تنجيد وتلابيس',
            default => trim($name),
        };
    }

    /**
     * عرض الأقسام المحذوفة
     */
    public function trash(Store $store)
    {
        $query = Category::onlyTrashed()->where('store_id', $store->id);
        if (! app(SupportSessionService::class)->active()) {
            $query->whereNotIn('id', app(AdministrativeArchiveService::class)->archivedIds(Category::class));
        }
        $categories = $query->with('archivedItem')->get();

        return view('user.stores.categories.trash', compact('store', 'categories'));
    }

    /**
     * استرجاع قسم محذوف
     */
    public function restore(Store $store, $id)
    {
        $category = Category::onlyTrashed()
            ->where('store_id', $store->id)
            ->where('id', $id)
            ->firstOrFail();

        $archive = app(AdministrativeArchiveService::class)->activeFor($category, $category->id);
        if ($archive) {
            abort_unless(app(SupportSessionService::class)->active(), 404);
            $conflict = Category::withTrashed()
                ->where('store_id', $store->id)
                ->where('id', '!=', $category->id)
                ->where(fn ($query) => $query->where('name', $archive->original_name)
                    ->orWhere('slug', $archive->original_slug))
                ->exists();
            if ($conflict) {
                $archive->update(['admin_message' => 'تعذرت استعادة القسم لوجود قسم مطابق في الاسم أو الرابط.']);
                return back()->with('error', $archive->admin_message);
            }

            DB::transaction(function () use ($category, $archive) {
                $locked = Category::withTrashed()->lockForUpdate()->findOrFail($category->id);
                $lockedArchive = ArchivedItem::lockForUpdate()->findOrFail($archive->id);
                $locked->name = $lockedArchive->original_name ?: $locked->name;
                $locked->slug = $lockedArchive->original_slug ?: $locked->slug;
                $locked->restore();
                $lockedArchive->update([
                    'status' => 'restored', 'restored_at' => now(),
                    'restored_by' => app(SupportSessionService::class)->active()?->admin_id,
                    'admin_message' => 'تمت استعادة القسم بواسطة الدعم التقني.',
                ]);
            });

            return back()->with('success', 'تم استرجاع القسم بواسطة الدعم التقني');
        }

        $category->restore();

        return redirect()
            ->route('user.stores.categories.trash', $store->id)
            ->with('success', 'تم استرجاع القسم بنجاح');
    }

    /**
     * حذف نهائي
     */
    public function forceDelete(Store $store, $id)
    {
        $category = Category::onlyTrashed()
            ->where('store_id', $store->id)
            ->where('id', $id)
            ->firstOrFail();

        $archiveService = app(AdministrativeArchiveService::class);
        $archive = $archiveService->activeFor($category, $category->id);
        if (! app(SupportSessionService::class)->active() || ! $archive) {
            $archiveService->archive($category, $store->user_id, $store->id, $category->name, $category->slug);
            return back()->with('success', 'تم حذف القسم نهائيًا من حسابك. يمكن طلب استعادته من الدعم التقني خلال 30 يومًا.');
        }

        if (Product::withTrashed()->where('category_id', $category->id)->exists()) {
            $archive->update(['admin_message' => 'تعذر الحذف الفعلي لأن القسم مرتبط بمنتجات.']);
            return back()->with('error', $archive->admin_message);
        }

        DB::transaction(function () use ($category, $archive) {
            Category::withTrashed()->lockForUpdate()->findOrFail($category->id)->forceDelete();
            ArchivedItem::lockForUpdate()->findOrFail($archive->id)->update([
                'status' => 'purged', 'admin_message' => 'حُذف القسم الفارغ فعلياً بواسطة الدعم التقني.',
            ]);
        });

        return redirect()
            ->route('user.stores.categories.trash', $store->id)
            ->with('success', 'تم حذف القسم الفارغ فعليًا بواسطة الدعم التقني');
    }

    /**
     * تفعيل/تعطيل القسم
     */
    public function toggleStatus(Store $store, Category $category)
    {
        if ($category->store_id != $store->id) {
            abort(403);
        }

        $newStatus = $category->status === 'active' ? 'inactive' : 'active';
        $category->update(['status' => $newStatus]);

        if ($newStatus === 'inactive') {
            $category->products()->update(['status' => 'inactive']);
        }

        return redirect()
            ->route('user.stores.categories.index', $store->id)
            ->with('success', 'تم تحديث حالة القسم');
    }

    /**
     * حذف القسم (Soft Delete)
     */
    public function destroy(Store $store, Category $category)
    {
        if ($category->store_id != $store->id) {
            abort(403);
        }

        DB::transaction(function () use ($store, $category) {
            // حذف أولي: نقل القسم والمنتجات التابعة له إلى سلة المحذوفات
            Product::where('store_id', $store->id)
                ->where('category_id', $category->id)
                ->delete();

            $category->delete();
        });

        return redirect()
            ->route('user.stores.categories.index', $store->id)
            ->with('success', 'تم حذف القسم ونقل منتجاته إلى سلة المحذوفات');
    }
}
