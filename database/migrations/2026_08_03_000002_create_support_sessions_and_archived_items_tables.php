<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->text('reason');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('started_ip', 45)->nullable();
            $table->string('ended_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index(['admin_id', 'ended_at']);
        });

        Schema::create('archived_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('archivable_type');
            $table->unsignedBigInteger('archivable_id');
            $table->string('original_name')->nullable();
            $table->string('original_slug')->nullable();
            $table->string('archived_slug')->nullable();
            $table->string('reference')->unique();
            $table->string('status')->default('archived');
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at');
            $table->timestamp('owner_restore_deadline')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['archivable_type', 'archivable_id']);
            $table->index(['owner_id', 'status']);
            $table->index(['status', 'owner_restore_deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_items');
        Schema::dropIfExists('support_sessions');
    }
};
