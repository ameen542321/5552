<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // 1. كم متراً تم قصها فعلياً في هذه العملية
            $table->decimal('custom_meters', 8, 2)->nullable()->after('custom_consumption');
            
            // 2. طول الرول الكامل للمنتج وقت حدوث هذه البيعة
            $table->decimal('roll_length_at_sale', 8, 2)->nullable()->after('custom_meters');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['custom_meters', 'roll_length_at_sale']);
        });
    }
};