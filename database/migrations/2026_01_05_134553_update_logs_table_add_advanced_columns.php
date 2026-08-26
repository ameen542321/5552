<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {

            // نوع الفاعل (user/admin/accountant)
            $table->string('actor_type')->nullable()->after('user_id');

            // الكيان المرتبط بالعملية
            $table->string('model_type')->nullable()->after('actor_type');
            $table->unsignedBigInteger('model_id')->nullable()->after('model_type');

            // تفاصيل إضافية
            $table->json('details')->nullable()->after('description');

            // معلومات الجهاز
            $table->string('ip')->nullable()->after('details');
            $table->string('user_agent')->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn([
                'actor_type',
                'model_type',
                'model_id',
                'details',
                'ip',
                'user_agent',
            ]);
        });
    }
};
