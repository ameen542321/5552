<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // سبب الإيقاف
            if (!Schema::hasColumn('users', 'suspension_reason')) {
                $table->string('suspension_reason')->nullable()->after('status');
            }

            // تاريخ نهاية الاشتراك
            if (!Schema::hasColumn('users', 'subscription_end_at')) {
                $table->date('subscription_end_at')->nullable()->after('suspension_reason');
            }

            // الخطة
            if (!Schema::hasColumn('users', 'plan_id')) {
                if (Schema::hasTable('plans')) {
                    $table->foreignId('plan_id')
                        ->nullable()
                        ->constrained('plans')
                        ->nullOnDelete()
                        ->after('subscription_end_at');
                } else {
                    // إذا جدول plans غير موجود
                    $table->unsignedBigInteger('plan_id')->nullable()->after('subscription_end_at');
                }
            }

            // عدد المتاجر المسموح بها
            if (!Schema::hasColumn('users', 'allowed_stores')) {
                $table->integer('allowed_stores')->default(1)->after('plan_id');
            }

            // عدد المحاسبين المسموح بهم
            if (!Schema::hasColumn('users', 'allowed_accountants')) {
                $table->integer('allowed_accountants')->default(1)->after('allowed_stores');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // حذف foreign key إذا موجود
            if (Schema::hasColumn('users', 'plan_id')) {
                try {
                    $table->dropForeign(['plan_id']);
                } catch (\Exception $e) {
                    // تجاهل الخطأ إذا لم يكن هناك FK
                }
            }

            // حذف الأعمدة
            $columns = [
                'suspension_reason',
                'subscription_end_at',
                'plan_id',
                'allowed_stores',
                'allowed_accountants',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
