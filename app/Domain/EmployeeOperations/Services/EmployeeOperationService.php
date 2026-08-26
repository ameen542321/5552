<?php

namespace App\Domain\EmployeeOperations\Services;

use App\Helpers\LogHelper;
use App\Models\Absence;
use App\Models\CreditSale;
use App\Models\Debt;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Withdrawal;
use App\Domain\EmployeeOperations\Actions\BuildEmployeeOperationActor;
use App\Domain\EmployeeOperations\Enums\EmployeeOperationStatus;
use App\Domain\EmployeeOperations\Exceptions\EmployeeOperationException;
use App\Services\EmployeeLogService;
use App\Services\NotificationService;
use App\Services\ShiftLifecycleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EmployeeOperationService
{
    public function recordWithdrawal(Model $person, array $data, array $actor, array $options = []): Withdrawal
    {
        $description = $this->nullableDescription($data['description'] ?? null);
        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($person->store_id, now());
        $operationDate = $this->operationDate($data['date'], $shiftContext, (bool) ($options['use_shift_gap_date'] ?? false));

        $exists = $person->withdrawals()
            ->whereDate('date', $operationDate->toDateString())
            ->where('amount', $data['amount'])
            ->where('description', $description)
            ->forAccountingDate($operationDate->toDateString())
            ->exists();

        if ($exists) {
            throw EmployeeOperationException::duplicate('لا يمكن تكرار نفس عملية السحب بنفس الوصف والقيمة في نفس اليوم.');
        }

        $withdrawal = $person->withdrawals()->create([
            'store_id' => $person->store_id,
            'person_id' => $person->id,
            'person_type' => get_class($person),
            'amount' => $data['amount'],
            'description' => $description,
            'date' => $operationDate->toDateString(),
            'status' => EmployeeOperationStatus::Pending->value,
            'month' => $operationDate->format('Y-m'),
            'business_date' => $shiftContext['business_date'],
            'daily_balance_id' => $shiftContext['daily_balance_id'],
            'added_by' => $actor['id'] ?? null,
        ]);

        EmployeeLogService::add(
            $person,
            'withdrawal',
            "سحب مبلغ {$data['amount']} ريال",
            $data['amount'],
            $this->operationLogMeta($actor, 'operation', $shiftContext['business_date'] ?? $operationDate->toDateString())
        );

        LogHelper::add(
            'withdrawal',
            "قام {$actor['name']} بتسجيل سحب بقيمة {$data['amount']} ريال للموظف {$person->name}",
            $person->store_id
        );

        return $withdrawal;
    }

    public function recordAbsence(Model $person, array $data, array $actor, array $options = []): Absence
    {
        $this->ensureActiveEmployeeForNewOperation($person, 'تسجيل غياب');

        $description = $this->nullableDescription($data['description'] ?? null);
        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($person->store_id, now());
        $operationDate = $this->operationDate($data['date'], $shiftContext, (bool) ($options['use_shift_gap_date'] ?? false));

        $exists = $person->absences()
            ->whereDate('date', $operationDate->toDateString())
            ->exists();

        if ($exists) {
            throw EmployeeOperationException::duplicate('تم تسجيل غياب لهذا المستخدم في هذا التاريخ مسبقًا');
        }

        $absence = $person->absences()->create([
            'store_id' => $person->store_id,
            'person_id' => $person->id,
            'person_type' => get_class($person),
            'date' => $operationDate->toDateString(),
            'description' => $description,
            'status' => EmployeeOperationStatus::Pending->value,
            'month' => $operationDate->format('Y-m'),
            'created_at' => $operationDate->copy()->setTimeFrom(now()),
            'added_by' => $actor['id'] ?? null,
        ]);

        EmployeeLogService::add(
            $person,
            'absence',
            "تسجيل غياب بتاريخ {$operationDate->toDateString()}",
            null,
            $this->operationLogMeta($actor, 'operation', $operationDate->toDateString())
        );

        LogHelper::add(
            'employee_absence',
            "قام {$actor['name']} بتسجيل غياب للموظف {$person->name} بتاريخ {$operationDate->toDateString()}",
            $person->store_id
        );

        if ((bool) ($options['notify_store_owner'] ?? false)) {
            NotificationService::sendTemplate('absence_recorded', [
                'sender_type' => 'CARLED',
                'target_type' => 'store',
                'target_ids' => [$person->store_id],
            ]);
        }

        return $absence;
    }



    public function recordDebt(Model $person, array $data, array $actor, array $options = []): Debt
    {
        $description = $this->nullableDescription($data['description'] ?? null);
        $operationContext = $this->resolveOperationContext(
            $person->store_id,
            $data['date'],
            (bool) ($options['use_shift_gap_date'] ?? false),
            (bool) ($options['use_accounting_date'] ?? false)
        );
        $operationDate = $operationContext['operation_date'];

        $exists = Debt::where('store_id', $person->store_id)
            ->where('person_id', $person->id)
            ->where('person_type', get_class($person))
            ->where('amount', $data['amount'])
            ->where('description', $description)
            ->forOperationDate($operationDate->toDateString())
            ->exists();

        if ($exists) {
            throw EmployeeOperationException::duplicate('تم تسجيل المديونية مسبقًا بنفس البيانات في تاريخ العملية.');
        }

        $debt = $person->debts()->create([
            'store_id' => $person->store_id,
            'person_id' => $person->id,
            'person_type' => get_class($person),
            'amount' => $data['amount'],
            'description' => $description,
            'date' => $operationDate->toDateString(),
            'type' => 'normal',
            'status' => Debt::STATUS_PENDING,
            'month' => $operationDate->format('Y-m'),
            'created_at' => $operationDate->copy()->setTimeFrom(now()),
            'added_by' => $actor['id'] ?? null,
        ]);

        EmployeeLogService::add(
            $person,
            'debt',
            "تسجيل مديونية بقيمة {$data['amount']} ريال",
            $data['amount'],
            $this->operationLogMeta($actor, 'operation', $operationDate->toDateString())
        );

        LogHelper::add(
            'employee_debt',
            "قام {$actor['name']} بتسجيل مديونية بقيمة {$data['amount']} ريال على الموظف {$person->name}",
            $person->store_id
        );

        if ((bool) ($options['notify_store_owner'] ?? false)) {
            $this->notifyStoreOwner($person, $actor, 'تسجيل مديونية', "قام {$actor['name']} بتسجيل مديونية بقيمة {$data['amount']} ريال على الموظف {$person->name}", 'debt_add');
        }

        return $debt;
    }

    public function recordCreditSale(Model $person, array $data, array $actor, array $options = []): CreditSale
    {
        $this->ensureActiveEmployeeForNewOperation($person, 'إضافة أجل');

        $description = $this->nullableDescription($data['description'] ?? null);
        $creditNote = $this->nullableDescription($data['operation_name'] ?? $data['credit_note'] ?? null);
        $saleId = isset($data['sale_id']) ? (int) $data['sale_id'] : (isset($options['sale_id']) ? (int) $options['sale_id'] : null);
        $operationContext = $this->resolveOperationContext(
            $person->store_id,
            $data['date'],
            (bool) ($options['use_shift_gap_date'] ?? false)
        );
        $operationDate = $operationContext['operation_date'];

        $exists = CreditSale::where('store_id', $person->store_id)
            ->where('person_id', $person->id)
            ->where('person_type', get_class($person))
            ->where('amount', $data['amount'])
            ->where('description', $description)
            ->where('credit_note', $creditNote)
            ->when($saleId, function ($query) use ($saleId) {
                $query->where(function ($saleQuery) use ($saleId) {
                    $saleQuery->where('sale_id', $saleId)
                        ->orWhere('description', 'like', '%#' . $saleId . '%');
                });
            })
            ->forOperationDate($operationDate->toDateString())
            ->exists();

        if ($exists) {
            throw EmployeeOperationException::duplicate('تم تسجيل البيع الآجل مسبقًا بنفس البيانات في تاريخ العملية.');
        }

        $creditSale = $person->creditSales()->create([
            'store_id' => $person->store_id,
            'sale_id' => $saleId,
            'person_id' => $person->id,
            'person_type' => get_class($person),
            'amount' => $data['amount'],
            'remaining_amount' => $data['amount'],
            'description' => $description,
            'credit_note' => $creditNote,
            'date' => $operationDate->toDateString(),
            'status' => CreditSale::STATUS_PENDING,
            'month' => $operationDate->format('Y-m'),
            'added_by' => $actor['id'] ?? null,
        ]);

        EmployeeLogService::add(
            $person,
            'credit_sale',
            "تسجيل بيع آجل بقيمة {$data['amount']} ريال",
            $data['amount'],
            $this->operationLogMeta($actor, 'operation', $operationDate->toDateString())
        );

        LogHelper::add(
            'credit_sale',
            "قام {$actor['name']} بتسجيل بيع آجل بقيمة {$data['amount']} ريال على الموظف {$person->name}",
            $person->store_id
        );

        return $creditSale;
    }


    public function collectDebt(Debt $debt, float $amount, array $actor, array $options = []): Debt
    {
        return DB::transaction(function () use ($debt, $amount, $actor, $options): Debt {
            $lockedDebt = Debt::query()
                ->whereKey($debt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $person = $lockedDebt->person;
            if (! $person) {
                throw EmployeeOperationException::duplicate('لم يتم العثور على صاحب المديونية.');
            }

            // الحماية هنا مهمة لأن جدول المديونيات يخزن نوعين من السجلات:
            // 1) مديونية أصلية بمبلغ موجب يمكن تحصيله.
            // 2) حركة تحصيل بمبلغ سالب لتوثيق المبلغ المقبوض.
            // لذلك لا نسمح بتحصيل السجلات السالبة أو المديونيات التي تم تسويتها حتى لا يحدث تحصيل مزدوج.
            if ((float) $lockedDebt->amount <= 0 || $lockedDebt->status === Debt::STATUS_DEDUCTED) {
                throw EmployeeOperationException::duplicate('تم تحصيل هذه المديونية مسبقًا أو أنها ليست مديونية أصلية قابلة للتحصيل.');
            }

            if ($amount <= 0 || $amount > (float) $lockedDebt->amount) {
                throw EmployeeOperationException::duplicate('مبلغ التحصيل غير صالح.');
            }

            $operationContext = $this->resolveOperationContext(
                $person->store_id,
                $options['date'] ?? now()->toDateString(),
                (bool) ($options['use_shift_gap_date'] ?? false),
                (bool) ($options['use_accounting_date'] ?? false)
            );
            $operationDate = $operationContext['operation_date'];
            $collectionKind = (bool) ($options['full'] ?? false) ? 'تحصيل كامل' : 'تحصيل جزئي';
            $collectionDescription = $this->nullableDescription($lockedDebt->description) ?: $collectionKind;
            $requestedPaymentMethod = $options['payment_method'] ?? 'cash';
            $paymentMethod = in_array($requestedPaymentMethod, ['cash', 'card', 'mixed'], true)
                ? $requestedPaymentMethod
                : 'cash';
            $cashAmount = $paymentMethod === 'card' ? 0.0 : ($paymentMethod === 'mixed' ? (float) ($options['cash_amount'] ?? 0) : $amount);
            $cardAmount = $paymentMethod === 'cash' ? 0.0 : ($paymentMethod === 'mixed' ? (float) ($options['card_amount'] ?? 0) : $amount);
            if (abs(($cashAmount + $cardAmount) - $amount) > 0.01) {
                throw EmployeeOperationException::duplicate('يجب أن يساوي مجموع الكاش والشبكة مبلغ تحصيل المديونية.');
            }
            $paymentMethodLabel = match ($paymentMethod) {
                'card' => 'شبكة',
                'mixed' => 'ميكس',
                default => 'كاش',
            };

            // فحص التكرار يجب أن يكون على نفس أصل المديونية فقط.
            // مثال: إذا كان لدى الموظف دينان منفصلان وتم تحصيل 25 ريالًا من كل واحد في نفس اليوم،
            // فهذا ليس تكرارًا. التكرار الحقيقي هو إرسال نفس تحصيل الـ 25 ريالًا لنفس الدين مرتين.
            $collectionExists = Debt::where('store_id', $person->store_id)
                ->where('person_id', $person->id)
                ->where('person_type', get_class($person))
                ->where('debt_parent_id', $lockedDebt->id)
                ->where('amount', -$amount)
                ->where('description', $collectionDescription)
                ->forOperationDate($operationDate->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($collectionExists) {
                throw EmployeeOperationException::duplicate('تم تسجيل هذا التحصيل مسبقًا في تاريخ العملية.');
            }

            // نسجل التحصيل كسطر مستقل بمبلغ سالب، ونربطه بالدين الأصلي عبر debt_parent_id
            // حتى تبقى التقارير قادرة على معرفة الدين الذي تم السداد منه بدل الاعتماد على التاريخ/المبلغ فقط.
            $collectionDebt = $person->debts()->create([
                'store_id' => $person->store_id,
                'person_id' => $person->id,
                'person_type' => get_class($person),
                'debt_parent_id' => $lockedDebt->id,
                'amount' => -$amount,
                'description' => $collectionDescription,
                'payment_method' => $paymentMethod,
                'payment_method_label' => $paymentMethodLabel,
                'cash_amount' => $cashAmount,
                'card_amount' => $cardAmount,
                'date' => $operationDate->toDateString(),
                'type' => 'normal',
                'status' => Debt::STATUS_PENDING,
                'month' => $operationDate->format('Y-m'),
                'added_by' => $actor['id'] ?? null,
            ]);

            $remainingAmount = max(0, (float) $lockedDebt->amount - $amount);
            $lockedDebt->update([
                'amount' => $remainingAmount,
                'status' => $remainingAmount <= 0 ? Debt::STATUS_DEDUCTED : Debt::STATUS_PENDING,
            ]);

            $actionName = (bool) ($options['full'] ?? false) ? 'debt_collect_full' : 'debt_collect_partial';
            EmployeeLogService::add(
                $person,
                $actionName,
                "{$collectionKind} بقيمة {$amount} ريال" . ($collectionDescription !== $collectionKind ? " - {$collectionDescription}" : ''),
                $amount,
                array_merge($this->operationLogMeta($actor, 'operation', $operationDate->toDateString()), [
                    'payment_method' => $paymentMethod,
                    'payment_method_label' => $paymentMethodLabel,
                    'cash_amount' => $cashAmount,
                    'card_amount' => $cardAmount,
                ])
            );

            LogHelper::add(
                'employee_' . $actionName,
                "قام {$actor['name']} بـ{$collectionKind} بقيمة {$amount} ريال من مديونية الموظف {$person->name}",
                $person->store_id
            );

            if ((bool) ($options['notify_store_owner'] ?? false)) {
                $this->notifyStoreOwner($person, $actor, $collectionKind . ' للمديونية', "قام {$actor['name']} بـ{$collectionKind} بقيمة {$amount} ريال من مديونية الموظف {$person->name}", $actionName);
            }

            return $collectionDebt;
        });
    }

    public function collectCreditSale(CreditSale $creditSale, float $amount, array $actor, array $options = []): CreditSale
    {
        return DB::transaction(function () use ($creditSale, $amount, $actor, $options): CreditSale {
            $lockedCreditSale = CreditSale::query()
                ->whereKey($creditSale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $person = $lockedCreditSale->person;
            if (! $person) {
                throw EmployeeOperationException::duplicate('لم يتم العثور على صاحب البيع الآجل.');
            }

            if ($lockedCreditSale->status === CreditSale::STATUS_DEDUCTED) {
                throw EmployeeOperationException::duplicate('تم تحصيل البيع الآجل مسبقًا.');
            }

            if ($amount <= 0 || $amount > (float) $lockedCreditSale->remaining_amount) {
                throw EmployeeOperationException::duplicate('مبلغ التحصيل غير صالح.');
            }

            $operationContext = $this->resolveOperationContext(
                $person->store_id,
                $options['date'] ?? now()->toDateString(),
                (bool) ($options['use_shift_gap_date'] ?? false)
            );
            $operationDate = $operationContext['operation_date'];
            $remainingAmount = max(0, (float) $lockedCreditSale->remaining_amount - $amount);
            $isFullyCollected = $remainingAmount == 0;
            $requestedPaymentMethod = $options['payment_method'] ?? 'cash';
            $paymentMethod = in_array($requestedPaymentMethod, ['cash', 'card', 'mixed'], true)
                ? $requestedPaymentMethod
                : 'cash';
            $cashAmount = $paymentMethod === 'card' ? 0.0 : ($paymentMethod === 'mixed' ? (float) ($options['cash_amount'] ?? 0) : $amount);
            $cardAmount = $paymentMethod === 'cash' ? 0.0 : ($paymentMethod === 'mixed' ? (float) ($options['card_amount'] ?? 0) : $amount);
            $paymentMethodLabel = match ($paymentMethod) {
                'card' => 'شبكة',
                'mixed' => 'ميكس',
                default => 'كاش',
            };
            $lockedCreditSale->remaining_amount = $remainingAmount;
            $lockedCreditSale->status = $isFullyCollected ? CreditSale::STATUS_DEDUCTED : CreditSale::STATUS_PENDING;
            $lockedCreditSale->deducted_month = $isFullyCollected ? $operationDate->format('Y-m') : $lockedCreditSale->deducted_month;
            $lockedCreditSale->save();
            DB::table('employee_credit_collections')->insert([
                'credit_sale_id' => $lockedCreditSale->id,
                'store_id' => $lockedCreditSale->store_id,
                'sale_id' => $lockedCreditSale->resolveLinkedSaleId(),
                'person_id' => $person->id,
                'person_type' => get_class($person),
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_method_label' => $paymentMethodLabel,
                'cash_amount' => $cashAmount,
                'card_amount' => $cardAmount,
                'collection_date' => $operationDate->toDateString(),
                'collected_by' => $actor['id'] ?? null,
                'meta' => json_encode([
                    'added_by_name' => $actor['name'] ?? null,
                    'description' => ($isFullyCollected ? 'تحصيل كامل' : 'تحصيل جزئي') . ' - ' . $paymentMethodLabel,
                    'remaining_amount_after_collection' => $remainingAmount,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $lockedCreditSale->syncLinkedSaleCollectionState();

            $actionName = $isFullyCollected ? 'credit_sale_deducted' : 'credit_sale_partial';
            EmployeeLogService::add(
                $person,
                $actionName,
                ($isFullyCollected ? 'تحصيل كامل بيع آجل' : 'تحصيل جزئي من بيع آجل') . " بقيمة {$amount} ريال ({$paymentMethodLabel})",
                $amount,
                array_merge($this->operationLogMeta($actor, 'operation', $operationDate->toDateString()), [
                    'payment_method' => $paymentMethod,
                    'payment_method_label' => $paymentMethodLabel,
                    'cash_amount' => $cashAmount,
                    'card_amount' => $cardAmount,
                ])
            );

            LogHelper::add(
                $actionName,
                "قام {$actor['name']} بتحصيل {$amount} ريال من بيع آجل للموظف {$person->name}",
                $person->store_id
            );

            return $lockedCreditSale;
        });
    }

    public function resolveOperationContext(int $storeId, string $requestedDate, bool $useShiftGapDate = false, bool $useAccountingDate = false): array
    {
        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($storeId, now());

        return [
            'shift_context' => $shiftContext,
            'operation_date' => $this->operationDate($requestedDate, $shiftContext, $useShiftGapDate, $useAccountingDate),
        ];
    }

    public function actorFromCurrentAuth(): array
    {
        return app(BuildEmployeeOperationActor::class)->fromCurrentAuth()->toArray();
    }


    private function notifyStoreOwner(Model $person, array $actor, string $title, string $message, string $templateKey): void
    {
        if (! $person instanceof Employee || ! $person->store?->user) {
            return;
        }

        Notification::create([
            'sender_id' => $actor['id'] ?? null,
            'sender_type' => $actor['type'] ?? 'system',
            'target_type' => 'user',
            'target_ids' => [$person->store->user->id],
            'title' => $title,
            'message' => $message,
            'template_key' => $templateKey,
            'channel' => 'CARLED',
        ]);
    }

    private function operationLogMeta(array $actor, string $type, ?string $operationDate = null): array
    {
        return [
            'type' => $type,
            'actor_id' => $actor['id'] ?? null,
            'actor_type' => $actor['type'] ?? null,
            'actor_name' => $actor['name'] ?? 'النظام',
            'operation_date' => $operationDate,
        ];
    }

    private function operationDate(string $requestedDate, array $shiftContext, bool $useShiftGapDate, bool $useAccountingDate = false): Carbon
    {
        if ($useAccountingDate) {
            return Carbon::parse($shiftContext['business_date'] ?? $requestedDate);
        }

        if ($useShiftGapDate && ($shiftContext['is_shift_gap_processing'] ?? false)) {
            return Carbon::parse($shiftContext['business_date']);
        }

        return Carbon::parse($requestedDate);
    }

    private function nullableDescription(?string $description): ?string
    {
        $description = trim((string) $description);

        return $description === '' ? null : $description;
    }

    private function ensureActiveEmployeeForNewOperation(Model $person, string $operationLabel): void
    {
        if ($person instanceof Employee && $person->status !== 'active') {
            throw new EmployeeOperationException("لا يمكن {$operationLabel} لموظف موقوف.");
        }
    }
}
