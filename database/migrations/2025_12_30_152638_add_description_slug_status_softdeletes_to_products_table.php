<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {

            // slug
            $table->string('slug')->unique()->after('name');

            // description
            $table->text('description')->nullable()->after('slug');

            // status
            $table->enum('status', ['active', 'inactive'])->default('active')->after('description');

            // soft deletes
            $table->softDeletes()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['slug', 'description', 'status']);
            $table->dropSoftDeletes();
        });
    }
};
