<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_credit_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_sale_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('person_id');
            $table->string('person_type');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20)->default('cash');
            $table->string('payment_method_label', 50)->nullable();
            $table->decimal('cash_amount', 12, 2)->default(0);
            $table->decimal('card_amount', 12, 2)->default(0);
            $table->date('collection_date');
            $table->unsignedBigInteger('collected_by')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['credit_sale_id', 'collection_date'], 'credit_collections_credit_date_index');
            $table->index(['store_id', 'collection_date'], 'credit_collections_store_date_index');
            $table->index(['person_id', 'person_type'], 'credit_collections_person_index');
            $table->index('sale_id', 'credit_collections_sale_id_index');
        });

        $this->backfillFromPartialPayments();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_credit_collections');
    }

    private function backfillFromPartialPayments(): void
    {
        if (! Schema::hasTable('credit_sales')) {
            return;
        }

        DB::table('credit_sales')
            ->whereNotNull('partial_payments')
            ->select('id', 'sale_id', 'store_id', 'person_id', 'person_type', 'partial_payments', 'updated_at', 'created_at')
            ->orderBy('id')
            ->chunkById(200, function ($creditSales): void {
                foreach ($creditSales as $creditSale) {
                    $payments = json_decode($creditSale->partial_payments ?: '[]', true);

                    if (! is_array($payments)) {
                        continue;
                    }

                    foreach ($payments as $payment) {
                        $amount = (float) ($payment['amount'] ?? 0);

                        if ($amount <= 0) {
                            continue;
                        }

                        $paymentMethod = in_array(($payment['payment_method'] ?? 'cash'), ['cash', 'card', 'mixed'], true)
                            ? $payment['payment_method']
                            : 'cash';
                        $cashAmount = (float) ($payment['cash_amount'] ?? ($paymentMethod === 'card' ? 0 : $amount));
                        $cardAmount = (float) ($payment['card_amount'] ?? ($paymentMethod === 'cash' ? 0 : ($paymentMethod === 'card' ? $amount : 0)));
                        $collectionDate = $payment['date'] ?? $this->optionalDate($creditSale->updated_at ?? $creditSale->created_at);

                        DB::table('employee_credit_collections')->insert([
                            'credit_sale_id' => $creditSale->id,
                            'sale_id' => $creditSale->sale_id,
                            'store_id' => $creditSale->store_id,
                            'person_id' => $creditSale->person_id,
                            'person_type' => $creditSale->person_type,
                            'amount' => $amount,
                            'payment_method' => $paymentMethod,
                            'payment_method_label' => $payment['payment_method_label'] ?? null,
                            'cash_amount' => $cashAmount,
                            'card_amount' => $cardAmount,
                            'collection_date' => $collectionDate,
                            'collected_by' => $payment['added_by'] ?? null,
                            'meta' => json_encode([
                                'added_by_name' => $payment['added_by_name'] ?? null,
                                'description' => $payment['description'] ?? null,
                                'source' => 'partial_payments_backfill',
                            ], JSON_UNESCAPED_UNICODE),
                            'created_at' => $creditSale->updated_at ?? now(),
                            'updated_at' => $creditSale->updated_at ?? now(),
                        ]);
                    }
                }
            });
    }

    private function optionalDate($value): string
    {
        return $value ? substr((string) $value, 0, 10) : now()->toDateString();
    }
};
