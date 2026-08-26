<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'purchase_name')) {
                $table->string('purchase_name')->nullable()->after('product_id');
            }

            if (!Schema::hasColumn('purchases', 'description')) {
                $table->string('description')->nullable()->after('cost');
            }

            if (Schema::hasColumn('purchases', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            }

            if (Schema::hasColumn('purchases', 'quantity')) {
                $table->decimal('quantity', 10, 2)->default(1)->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'purchase_name')) {
                $table->dropColumn('purchase_name');
            }

            if (Schema::hasColumn('purchases', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('purchases', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('purchases', 'quantity')) {
                $table->integer('quantity')->default(1)->change();
            }
        });
    }
};
