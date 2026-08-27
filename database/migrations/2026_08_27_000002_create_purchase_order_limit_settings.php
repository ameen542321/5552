<?php

use App\Modules\PurchaseOrders\Models\PurchaseOrderLimitSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل store_id = null يمثل الافتراضي العام، وبقية السجلات تخص متجرًا واحدًا.
        Schema::create('purchase_order_limit_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->nullable()->unique()->constrained('stores')->cascadeOnDelete();
            $table->unsignedSmallInteger('weekly_limit')->default(PurchaseOrderLimitSetting::DEFAULT_WEEKLY_LIMIT);
            $table->json('counted_statuses');
            $table->unsignedSmallInteger('exception_weekly_limit')->nullable();
            $table->timestamp('exception_expires_at')->nullable();
            $table->text('exception_reason')->nullable();
            $table->foreignId('exception_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // زرع الافتراضي يحافظ على الحد السابق (4) فور تشغيل migration.
        DB::table('purchase_order_limit_settings')->insert([
            'store_id' => null,
            'weekly_limit' => PurchaseOrderLimitSetting::DEFAULT_WEEKLY_LIMIT,
            'counted_statuses' => json_encode(PurchaseOrderLimitSetting::DEFAULT_COUNTED_STATUSES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_limit_settings');
    }
};
