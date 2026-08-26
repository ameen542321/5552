<?php

namespace App\Services;

use App\Models\ArchivedItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdministrativeArchiveService
{
    public function archive(
        Model $model,
        ?int $ownerId,
        ?int $storeId,
        ?string $name = null,
        ?string $slug = null
    ): ArchivedItem {
        return DB::transaction(function () use ($model, $ownerId, $storeId, $name, $slug) {
            $locked = $model->newQuery()->withTrashed()->lockForUpdate()->findOrFail($model->getKey());
            $archive = ArchivedItem::lockForUpdate()->firstOrNew([
                'archivable_type' => $model::class,
                'archivable_id' => $model->getKey(),
            ]);

            if (! $archive->exists) {
                $archive->fill([
                    'owner_id' => $ownerId,
                    'store_id' => $storeId,
                    'original_name' => $name,
                    'original_slug' => $slug,
                    'reference' => 'ARC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8)),
                    'archived_by' => app(OperationActorContext::class)->realActor()?->getAuthIdentifier(),
                    'archived_at' => now(),
                    'owner_restore_deadline' => now()->addDays(30),
                ]);
            }

            if ($slug !== null && isset($locked->slug)) {
                $archivedSlug = $slug . '--archived-' . $locked->getKey();
                $locked->slug = $archivedSlug;
                $locked->saveQuietly();
                $archive->archived_slug = $archivedSlug;
            }

            $archive->status = 'archived';
            $archive->save();

            app(LogService::class)->add('administrative_archive_created', 'تم حذف السجل نهائيًا من حساب المالك مع إبقاء إمكانية الاستعادة المؤقتة.', $locked, [
                'archive_reference' => $archive->reference,
                'archivable_type' => $model::class,
                'owner_restore_deadline' => $archive->owner_restore_deadline?->toDateTimeString(),
            ]);

            return $archive;
        });
    }

    public function activeFor(Model|string $model, int $id): ?ArchivedItem
    {
        $type = is_string($model) ? $model : $model::class;

        return ArchivedItem::where('archivable_type', $type)
            ->where('archivable_id', $id)
            ->where('status', 'archived')
            ->first();
    }

    public function archivedIds(string $modelType)
    {
        return ArchivedItem::where('archivable_type', $modelType)
            ->where('status', 'archived')
            ->select('archivable_id');
    }
}
