<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('type'); // withdrawal, absence, debt, credit_sale, store_transfer, salary_update...

            $table->decimal('amount', 10, 2)->nullable(); // لبعض العمليات (سحب، مديونية، بيع آجل...)
            $table->text('description')->nullable();      // وصف العملية بالعربي
            $table->timestamp('logged_at');               // وقت حدوث العملية

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_logs');
    }
};
