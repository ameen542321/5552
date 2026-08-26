<?php

namespace App\Services\Shifts;

use App\Models\Accountant;
use App\Models\Log;
use App\Models\Notification;
use App\Models\Store;
use App\Models\User;
use App\Services\LogService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftGapRequestService
{
    /**
     * يرجع حالة الطلب النشط لشفت ناقص محدد، أو null إذا لا يوجد طلب نشط.
     */
    public function activeStatus(int $storeId, string $businessDate, ?int $missingShiftNumber = null): ?string
    {
        return data_get($this->activeDetails($storeId, $businessDate, $missingShiftNumber), 'status');
    }

    /**
     * يرجع بيانات الطلب النشط اللازمة لعرض المحاسب المرتبط بالشفت.
     */
    public function activeDetails(int $storeId, string $businessDate, ?int $missingShiftNumber = null): ?array
    {
        $activeRequestLog = $this->activeRequestLog($storeId, $businessDate, $missingShiftNumber);

        if (! $activeRequestLog) {
            return null;
        }

        return [
            'status' => data_get($activeRequestLog->details, 'status', 'pending'),
            'accountant_id' => data_get($activeRequestLog->details, 'accountant_id'),
            'accountant_name' => data_get($activeRequestLog->details, 'accountant_name'),
        ];
    }

    /**
     * يبني خريطة حالات الطلبات النشطة لصفوف الشفتات الناقصة بصيغة business_date#shift_number.
     */
    public function activeStatusesForMissingRows(int $storeId, Collection $missingRows): array
    {
        if ($missingRows->isEmpty()) {
            return [];
        }

        $missingKeys = $missingRows->mapWithKeys(fn ($row) => [$row['date'].'#'.$row['missing_shift_number'] => true]);

        return Log::query()
            ->where('store_id', $storeId)
            ->where('action', 'shift_gap_accountant_request')
            ->latest()
            ->limit(50)
            ->get()
            ->reduce(function (array $statuses, Log $log) use ($missingKeys) {
                $businessDate = data_get($log->details, 'business_date');
                $status = data_get($log->details, 'status', 'pending');
                $shiftNumber = (int) data_get($log->details, 'missing_shift_number', 1);

                if (! $businessDate || ! in_array($status, ['pending', 'in_progress'], true)) {
                    return $statuses;
                }

                $date = Carbon::parse($businessDate)->toDateString();
                $key = $date.'#'.$shiftNumber;
                if (isset($missingKeys[$key]) && ! isset($statuses[$key])) {
                    $statuses[$key] = $status;
                }

                return $statuses;
            }, []);
    }

    /**
     * ينشئ طلب مراجعة شفت ناقص من المالك ويخطر المحاسب المختار.
     *
     * هذه هي الدالة الأساسية الجديدة لإنشاء طلبات الشفت الناقص بدل وضع
     * تفاصيل السجل والإشعار داخل StoreController.
     */
    public function createOwnerRequest(
        Store $store,
        User $owner,
        Accountant $accountant,
        string $businessDate,
        array $shiftInfo
    ): void {
        $missingShiftNumber = (int) $shiftInfo['missing_shift_number'];
        $maxShifts = (int) $shiftInfo['max_shifts'];
        $closedShiftsCount = (int) $shiftInfo['closed_shifts_count'];
        $shiftLabel = 'الشفت ' . $missingShiftNumber . ' من ' . $maxShifts;
        $shiftKey = $businessDate . '#' . $missingShiftNumber;

        app(LogService::class)->add(
            'shift_gap_accountant_request',
            'طلب المالك من المحاسب ' . $accountant->name . ' مراجعة وإدخال بيانات ' . $shiftLabel . ' الناقص بتاريخ ' . $businessDate,
            $store,
            [
                'business_date' => $businessDate,
                'status' => 'pending',
                'requested_at' => now()->toDateTimeString(),
                'accountant_id' => (int) $accountant->id,
                'accountant_name' => $accountant->name,
                'closed_shifts_count' => $closedShiftsCount,
                'missing_shift_number' => $missingShiftNumber,
                'max_shifts' => $maxShifts,
                'shift_label' => $shiftLabel,
                'shift_key' => $shiftKey,
            ]
        );

        $this->notifyAccountant(
            $store,
            $owner,
            $accountant,
            'طلب معالجة شفت ناقص',
            'طلب المالك معالجة ' . $shiftLabel . ' بتاريخ ' . $businessDate . ' في متجر ' . $store->name,
            'shift_gap_accountant_request',
            $businessDate,
            $missingShiftNumber,
            $shiftKey
        );
    }


    /**
     * يلغي طلب مراجعة شفت ناقص نشط حتى يستطيع المالك إعادة إرساله لمحاسب آخر.
     */
    public function cancelOwnerRequest(Store $store, User $owner, string $businessDate, int $missingShiftNumber): bool
    {
        $activeRequestLog = $this->activeRequestLog($store->id, $businessDate, $missingShiftNumber);

        if (! $activeRequestLog) {
            return false;
        }

        $requestDetails = $activeRequestLog->details ?: [];
        $previousStatus = (string) data_get($requestDetails, 'status', 'pending');
        $requestDetails['status'] = 'canceled';
        $requestDetails['canceled_at'] = now()->toDateTimeString();
        $requestDetails['canceled_by'] = (int) $owner->id;

        $activeRequestLog->forceFill(['details' => $requestDetails])->save();

        $shiftLabel = (string) data_get($requestDetails, 'shift_label', 'الشفت ' . $missingShiftNumber);
        app(LogService::class)->add(
            'shift_gap_accountant_request_canceled',
            'إلغاء طلب معالجة ' . $shiftLabel . ' بتاريخ ' . $businessDate,
            $store,
            [
                'business_date' => $businessDate,
                'status' => 'canceled',
                'previous_status' => $previousStatus,
                'canceled_at' => $requestDetails['canceled_at'],
                'canceled_by' => (int) $owner->id,
                'accountant_id' => data_get($requestDetails, 'accountant_id'),
                'accountant_name' => data_get($requestDetails, 'accountant_name'),
                'missing_shift_number' => $missingShiftNumber,
                'shift_key' => $businessDate . '#' . $missingShiftNumber,
            ]
        );

        return true;
    }


    /**
     * يعيد تعيين طلب شفت ناقص نشط إلى محاسب آخر مع حفظ أثر التدقيق وإشعار المحاسب الجديد.
     */
    public function reassignOwnerRequest(
        Store $store,
        User $owner,
        Accountant $newAccountant,
        string $businessDate,
        int $missingShiftNumber
    ): bool {
        $activeRequestLog = $this->activeRequestLog($store->id, $businessDate, $missingShiftNumber);

        if (! $activeRequestLog) {
            return false;
        }

        $requestDetails = $activeRequestLog->details ?: [];
        $previousAccountantId = data_get($requestDetails, 'accountant_id');
        $previousAccountantName = data_get($requestDetails, 'accountant_name');
        $shiftLabel = (string) data_get($requestDetails, 'shift_label', 'الشفت ' . $missingShiftNumber);
        $shiftKey = $businessDate . '#' . $missingShiftNumber;

        $requestDetails['status'] = 'pending';
        $requestDetails['accountant_id'] = (int) $newAccountant->id;
        $requestDetails['accountant_name'] = $newAccountant->name;
        $requestDetails['reassigned_at'] = now()->toDateTimeString();
        $requestDetails['reassigned_by'] = (int) $owner->id;
        $requestDetails['previous_accountant_id'] = $previousAccountantId;
        $requestDetails['previous_accountant_name'] = $previousAccountantName;

        $activeRequestLog->forceFill(['details' => $requestDetails])->save();

        app(LogService::class)->add(
            'shift_gap_accountant_request_reassigned',
            'إعادة تعيين طلب معالجة ' . $shiftLabel . ' بتاريخ ' . $businessDate . ' إلى المحاسب ' . $newAccountant->name,
            $store,
            [
                'business_date' => $businessDate,
                'status' => 'pending',
                'reassigned_at' => $requestDetails['reassigned_at'],
                'reassigned_by' => (int) $owner->id,
                'previous_accountant_id' => $previousAccountantId,
                'previous_accountant_name' => $previousAccountantName,
                'accountant_id' => (int) $newAccountant->id,
                'accountant_name' => $newAccountant->name,
                'missing_shift_number' => $missingShiftNumber,
                'shift_key' => $shiftKey,
            ]
        );

        $this->notifyAccountant(
            $store,
            $owner,
            $newAccountant,
            'إعادة تعيين طلب شفت ناقص',
            'تم تعيينك لمعالجة ' . $shiftLabel . ' بتاريخ ' . $businessDate . ' في متجر ' . $store->name,
            'shift_gap_accountant_request_reassigned',
            $businessDate,
            $missingShiftNumber,
            $shiftKey
        );

        return true;
    }


    private function notifyAccountant(
        Store $store,
        User $owner,
        Accountant $accountant,
        string $title,
        string $message,
        string $templateKey,
        string $businessDate,
        int $missingShiftNumber,
        string $shiftKey
    ): void {
        Notification::create([
            'sender_id' => (int) $owner->id,
            // جدول notifications لا يقبل store_owner في enum الحالي؛ المالك مستخدم عادي مع توضيح الدور داخل data.
            'sender_type' => 'user',
            'target_type' => 'accountants',
            'target_ids' => [(int) $accountant->id],
            'title' => $title,
            'message' => $message,
            'data' => [
                'store_id' => (int) $store->id,
                'business_date' => $businessDate,
                'missing_shift_number' => $missingShiftNumber,
                'shift_key' => $shiftKey,
                'sender_role' => 'store_owner',
            ],
            'template_key' => $templateKey,
            'channel' => 'site',
        ]);
    }

    /**
     * يبحث عن آخر طلب نشط لنفس المتجر والتاريخ ورقم الشفت.
     */
    private function activeRequestLog(int $storeId, string $businessDate, ?int $missingShiftNumber = null): ?Log
    {
        return Log::query()
            ->where('store_id', $storeId)
            ->where('action', 'shift_gap_accountant_request')
            ->latest()
            ->limit(50)
            ->get()
            ->first(function (Log $log) use ($businessDate, $missingShiftNumber) {
                $logBusinessDate = data_get($log->details, 'business_date');
                $logStatus = data_get($log->details, 'status', 'pending');
                $logShiftNumber = data_get($log->details, 'missing_shift_number');

                return $logBusinessDate
                    && Carbon::parse($logBusinessDate)->toDateString() === $businessDate
                    && (! $missingShiftNumber || ! $logShiftNumber || (int) $logShiftNumber === $missingShiftNumber)
                    && in_array($logStatus, ['pending', 'in_progress'], true);
            });
    }

}
