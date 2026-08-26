<?php

namespace App\Services\Accounting;

use App\Models\Accountant;
use App\Models\Employee;
use App\Models\User;
use App\Support\PaymentTypeLabel;
use Illuminate\Support\Str;

class AccountingOperationPresenter
{
    public function lastOperation(object $operation, string $operationType): object
    {
        $employeeName = $this->employeeNameForLastOperation($operation, $operationType);
        $actorName = $this->executorName($operation) ?: ($employeeName !== '--' ? $employeeName : null);
        $description = $operation->description ?? $operation->reason ?? $operation->note ?? ($actorName ?: 'غير محدد');

        if (in_array($operationType, ['expense', 'withdrawal'], true) && empty($operation->description)) {
            $description = $actorName ?: 'غير محدد';
        }

        $amount = $operation->amount ?? 0;

        if ($operationType === 'sale') {
            $saleDescription = trim((string) $operation->description);
            $description = $this->saleOperationLabel($operation, $saleDescription);
            $amount = (float) ($operation->paid_amount ?? 0);
            $employeeName = $operation->accountant?->name ?: ($employeeName !== '--' ? $employeeName : 'المحاسب');
            if (($operation->sale_type ?? null) === 'credit' || (bool) ($operation->has_partial_credit ?? false)) {
                $creditAmount = (float) ($operation->remaining_amount ?? 0);
                $totalAmount = (float) (($operation->operation_total ?? 0) ?: ($operation->final_total ?? 0));
                $description = trim($description . ' — ' . (($operation->sale_type ?? null) === 'credit' ? 'بيع آجل كامل' : 'بيع آجل جزئي') . ' / الأجل: ' . number_format($creditAmount, 2) . ' / الإجمالي: ' . number_format($totalAmount, 2));
            }
        } elseif (in_array($operationType, ['expense', 'withdrawal'], true) && ($employeeName === '--' || $employeeName === 'نظام')) {
            $employeeName = $actorName && $actorName !== '--' ? $actorName : ($operationType === 'expense' ? 'منفذ المصروف' : 'منفذ السحب');
        }

        $accountingDate = $this->accountingDateForLastOperation($operation, $operationType);
        $entryTime = optional($operation->created_at)->format('H:i:s') ?? '00:00:00';

        return (object) [
            'type' => $operationType,
            'employee' => $employeeName,
            'description' => Str::limit($description, 30),
            'amount' => $amount,
            'created_at' => $operation->created_at,
            'operation_date' => $accountingDate,
            'sort_key' => $accountingDate->format('Y-m-d') . ' ' . $entryTime,
            'formatted_time' => optional($operation->created_at)->format('h:i A') ?? '--',
            'formatted_date' => $accountingDate->format('Y-m-d'),
        ];
    }

    public function saleDetail(object $sale): array
    {
        $description = trim((string) ($sale->description ?: $sale->internal_notes));
        $operationType = $this->saleOperationType($sale, $description);

        $isFullCreditSale = ($sale->sale_type ?? null) === 'credit';
        $isPartialCreditSale = (bool) ($sale->has_partial_credit ?? false);
        $operationAmount = (float) (($sale->operation_total ?? 0) ?: ($sale->final_total ?? 0));
        $creditAmount = (float) ($sale->remaining_amount ?? 0);

        return [
            'time' => $sale->created_at,
            'operation_type' => $isFullCreditSale ? 'بيع آجل' : ($isPartialCreditSale ? 'بيع آجل جزئي' : $operationType['type']),
            'product' => $operationType['label'],
            'amount' => (float) ($sale->paid_amount ?? 0),
            'cost_amount' => (float) max(((float) $sale->products_total + (float) $sale->labor_total) - (float) $sale->profit, 0),
            'cash_amount' => (float) ($sale->sale_type === 'cash'
                ? $sale->paid_amount
                : ($sale->sale_type === 'mixed' ? $sale->cash_amount : 0)),
            'card_amount' => (float) ($sale->sale_type === 'card'
                ? $sale->paid_amount
                : ($sale->sale_type === 'mixed' ? $sale->card_amount : 0)),
            'payment_type' => PaymentTypeLabel::dashboardLabel($sale->sale_type),
            'note' => $description ?: null,
            'actor' => $sale->accountant?->name ?: 'المحاسب',
            'employee_name' => optional($sale->employee)->name,
            'operation_amount' => $operationAmount,
            'credit_amount' => $creditAmount,
            'remaining_amount' => $creditAmount,
        ];
    }

    public function creditCollectionDetail(array $collection): array
    {
        $product = $collection['operation_name'] ?? $collection['credit_note'] ?? $collection['description'] ?? (($collection['collection_kind'] ?? null) === 'debt' ? 'تحصيل مديونية' : 'تحصيل آجل');
        $note = $collection['description'] ?? null;
        if ($note === $product) {
            $note = null;
        }

        return [
            'time' => \Carbon\Carbon::parse($collection['collection_date'] ?? now()),
            'operation_type' => ($collection['type'] ?? null) === 'full_collection'
                ? (($collection['collection_kind'] ?? null) === 'debt' ? 'تحصيل مديونية كلي' : 'تحصيل آجل كلي')
                : (($collection['collection_kind'] ?? null) === 'debt' ? 'تحصيل مديونية جزئي' : 'تحصيل آجل جزئي'),
            'product' => $product,
            'amount' => (float) ($collection['collected_amount'] ?? $collection['amount'] ?? 0),
            'cash_amount' => (float) ($collection['cash_amount'] ?? $collection['collected_cash_amount'] ?? $collection['collected_cash'] ?? 0),
            'card_amount' => (float) ($collection['card_amount'] ?? $collection['collected_card_amount'] ?? $collection['collected_card'] ?? 0),
            'payment_type' => $collection['payment_method_label'] ?? 'تحصيل',
            'note' => $note,
            'actor' => $collection['actor_name'] ?? 'المحاسب',
            'employee_name' => $collection['employee_name'] ?? null,
            'operation_date' => $collection['operation_date'] ?? null,
            'operation_amount' => isset($collection['operation_amount']) ? (float) $collection['operation_amount'] : null,
            'credit_amount' => ($collection['collection_kind'] ?? null) === 'credit' && isset($collection['operation_amount']) ? (float) $collection['operation_amount'] : null,
            'debt_parent_amount' => isset($collection['debt_parent_amount']) ? (float) $collection['debt_parent_amount'] : null,
            'remaining_amount' => isset($collection['remaining_after_collection']) ? (float) $collection['remaining_after_collection'] : (isset($collection['remaining_before_collection']) ? max(0, (float) $collection['remaining_before_collection'] - (float) ($collection['amount'] ?? 0)) : null),
        ];
    }

    public function expenseDetail(object $expense): array
    {
        return [
            'time' => $expense->created_at,
            'operation_type' => 'مصروف',
            'product' => $expense->description ?: optional($expense->employee)->name ?: optional($expense->user)->name ?: $expense->type ?: 'مصروف',
            'amount' => (float) $expense->amount,
            'cash_amount' => 0,
            'card_amount' => 0,
            'payment_type' => 'مصروف',
            'note' => $expense->description,
            'actor' => $this->executorName($expense) ?: 'منفذ المصروف',
            'employee_name' => optional($expense->employee)->name,
        ];
    }

    public function withdrawalDetail(object $withdrawal): array
    {
        $employeeName = optional($withdrawal->person)->name;
        $description = trim((string) ($withdrawal->description ?? ''));

        return [
            'time' => $withdrawal->created_at,
            'operation_type' => 'سحب',
            'product' => $employeeName ? 'سحب على الموظف' : 'سحب نقدي',
            'amount' => (float) $withdrawal->amount,
            'cash_amount' => 0,
            'card_amount' => 0,
            'payment_type' => 'سحب',
            'note' => $description !== '' ? $description : null,
            'actor' => optional($withdrawal->addedBy)->name ?: 'منفذ السحب',
            'employee_name' => $employeeName,
        ];
    }

    public function debtDetail(object $debt): array
    {
        return [
            'time' => $debt->created_at,
            'operation_type' => 'مديونية',
            'product' => $debt->description ?: 'مديونية',
            'amount' => (float) $debt->amount,
            'cash_amount' => 0,
            'card_amount' => 0,
            'payment_type' => 'مديونية',
            'note' => $debt->description,
        ];
    }

    private function accountingDateForLastOperation(object $operation, string $operationType): \Carbon\Carbon
    {
        $date = $operation->business_date
            ?? ($operationType === 'withdrawal' ? ($operation->date ?? null) : null)
            ?? $operation->created_at
            ?? now();

        return \Carbon\Carbon::parse($date);
    }

    private function employeeNameForLastOperation(object $operation, string $operationType): string
    {
        $personId = $operationType === 'withdrawal'
            ? ($operation->person_id ?? null)
            : ($operation->employee_id ?? null);

        if (! $personId) {
            return '--';
        }

        $relation = $operationType === 'withdrawal' ? 'person' : 'employee';
        if (method_exists($operation, $relation) && $operation->{$relation}) {
            return $operation->{$relation}->name ?? '--';
        }

        $employee = Employee::find($personId);

        return $employee ? $employee->name : 'موظف #' . $personId;
    }

    private function executorName(object $operation): ?string
    {
        if ($operation->accountant?->name) {
            return $operation->accountant->name;
        }

        $expenseActorName = $this->expenseActorName($operation);
        if ($expenseActorName) {
            return $expenseActorName;
        }

        if ($operation->user?->name) {
            return $operation->user->name;
        }

        if ($operation->addedBy?->name) {
            return $operation->addedBy->name;
        }

        if (! empty($operation->added_by)) {
            $accountant = Accountant::find($operation->added_by);
            if ($accountant?->name) {
                return $accountant->name;
            }
        }

        if ($operation->employee?->name) {
            return $operation->employee->name;
        }

        return null;
    }

    private function expenseActorName(object $operation): ?string
    {
        $actorType = $operation->actor_type ?? null;
        $actorId = $operation->user_id ?? null;

        if (! $actorType || ! $actorId) {
            return null;
        }

        if ($actorType === 'accountant_expense') {
            return Accountant::find($actorId)?->name;
        }

        if ($actorType === 'owner_expense') {
            return $operation->user?->name ?: User::find($actorId)?->name;
        }

        return null;
    }

    private function saleOperationType(object $sale, string $description): array
    {
        $productNames = collect($sale->items ?? [])
            ->map(fn ($item) => optional($item->product)->name)
            ->filter()
            ->unique()
            ->values();

        if ($this->isTintOperation($description)) {
            return ['type' => 'تضليل', 'label' => $description];
        }

        if ($productNames->isNotEmpty()) {
            return ['type' => 'بيع منتجات', 'label' => $productNames->implode(' - ')];
        }

        if ((float) $sale->labor_total > 0) {
            return ['type' => 'شغل يد', 'label' => $description ?: 'شغل يد'];
        }

        return ['type' => 'عملية بيع', 'label' => $description ?: 'عملية بدون منتجات'];
    }

    private function saleOperationLabel(object $sale, string $description): string
    {
        return $this->saleOperationType($sale, $description)['label'] ?: 'عملية بيع بدون منتجات';
    }

    private function isTintOperation(string $description): bool
    {
        return mb_stripos($description, 'تضليل') !== false
            || mb_stripos($description, 'تظليل') !== false;
    }
}
