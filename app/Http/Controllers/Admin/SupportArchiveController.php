<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedItem;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Store;
use App\Services\LogService;
use App\Services\SupportSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class SupportArchiveController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:archived,restored,purged'],
            'type' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'in:active,expired'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = ArchivedItem::query()
            ->with(['owner', 'store'])
            ->latest('archived_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['type'])) {
            $query->where('archivable_type', $validated['type']);
        }

        if (($validated['deadline'] ?? null) === 'expired') {
            $query->where('status', 'archived')
                ->whereNotNull('owner_restore_deadline')
                ->where('owner_restore_deadline', '<', now());
        } elseif (($validated['deadline'] ?? null) === 'active') {
            $query->where('status', 'archived')
                ->where(function ($deadline) {
                    $deadline->whereNull('owner_restore_deadline')
                        ->orWhere('owner_restore_deadline', '>=', now());
                });
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($archive) use ($search) {
                $archive->where('reference', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%")
                    ->orWhere('admin_message', 'like', "%{$search}%")
                    ->orWhereHas('owner', function ($owner) use ($search) {
                        $owner->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $archives = $query->paginate(25)->withQueryString();
        $types = ArchivedItem::query()
            ->select('archivable_type')
            ->distinct()
            ->orderBy('archivable_type')
            ->pluck('archivable_type');
        $summary = [
            'active' => ArchivedItem::where('status', 'archived')->count(),
            'expired' => ArchivedItem::where('status', 'archived')
                ->whereNotNull('owner_restore_deadline')
                ->where('owner_restore_deadline', '<', now())
                ->count(),
            'restored' => ArchivedItem::where('status', 'restored')->count(),
            'purged' => ArchivedItem::where('status', 'purged')->count(),
        ];

        return view('admin.support-archive.index', compact('archives', 'types', 'summary'));
    }

    public function message(Request $request, ArchivedItem $archive)
    {
        $support = app(SupportSessionService::class)->active($request);
        abort_unless($support, 403);

        $targetOwnerId = auth('accountant')->user()?->user_id ?? auth('web')->id();
        abort_unless((int) $archive->owner_id === (int) $targetOwnerId, 403);

        $validated = $request->validate([
            'admin_message' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $archive->update(['admin_message' => $validated['admin_message']]);
        app(LogService::class)->add('archive_message_updated', 'حدّث الدعم التقني رسالة سجل المحذوفات.', $archive, [
            'archive_reference' => $archive->reference,
        ]);

        return back()->with('success', 'تم حفظ رسالة الدعم في سجل المحذوفات.');
    }

    public function review(
        Request $request,
        ArchivedItem $archive,
        SupportSessionService $sessions
    ): RedirectResponse {
        abort_unless($archive->status === 'archived', 422, 'هذه العملية مكتملة ولا تحتاج جلسة مراجعة جديدة.');

        $owner = $archive->owner;
        abort_unless($owner && ! $owner->isAdmin(), 422, 'تعذر بدء المراجعة لأن حساب المالك غير متاح.');

        $modelClass = $archive->archivable_type;
        abort_unless(in_array($modelClass, [
            Product::class, Category::class, Employee::class, Store::class, Purchase::class,
        ], true), 422, 'نوع السجل غير مدعوم.');
        $record = $modelClass::withTrashed()->find($archive->archivable_id);
        abort_unless($record, 422, 'السجل الأصلي غير متاح؛ راجع بيانات العملية قبل المتابعة.');
        abort_unless($this->recordBelongsToOwner($archive, $record), 422, 'بيانات المالك أو المتجر في السجل غير متطابقة.');

        $sessions->start(
            $request->user('web'),
            $owner,
            'مراجعة سجل المحذوفات ' . $archive->reference . ': ' . ($archive->original_name ?: $archive->type_label),
            $request
        );

        return redirect()->route($this->reviewRoute($archive), $this->reviewRouteParameters($archive));
    }

    private function reviewRoute(ArchivedItem $archive): string
    {
        return match ($archive->archivable_type) {
            Product::class => 'user.stores.products.trash',
            Category::class => 'user.stores.categories.trash',
            Employee::class => 'user.employees.trash',
            Store::class => 'user.stores.trash',
            Purchase::class => 'user.stores.internal-use.trash',
            default => 'user.dashboard',
        };
    }

    private function reviewRouteParameters(ArchivedItem $archive): array
    {
        return match ($archive->archivable_type) {
            Product::class, Category::class, Purchase::class => ['store' => $archive->store_id],
            default => [],
        };
    }

    private function recordBelongsToOwner(ArchivedItem $archive, \Illuminate\Database\Eloquent\Model $record): bool
    {
        if ($record instanceof Store) {
            return (int) $record->user_id === (int) $archive->owner_id
                && (int) $record->id === (int) $archive->store_id;
        }

        $recordStoreId = (int) $record->getAttribute('store_id');
        if (! $recordStoreId || $recordStoreId !== (int) $archive->store_id) {
            return false;
        }

        return Store::withTrashed()
            ->whereKey($recordStoreId)
            ->where('user_id', $archive->owner_id)
            ->exists();
    }
}
