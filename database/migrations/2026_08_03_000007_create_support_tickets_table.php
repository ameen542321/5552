<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accountant_id')->nullable()->constrained('accountants')->nullOnDelete();
            $table->string('requested_role', 30)->default('owner');
            $table->string('subject');
            $table->text('description');
            $table->string('status', 30)->default('open');
            $table->text('support_response')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('created_by_support')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('support_sessions', function (Blueprint $table) {
            $table->foreignId('support_ticket_id')->nullable()->after('id')
                ->constrained('support_tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('support_ticket_id');
        });
        Schema::dropIfExists('support_tickets');
    }
};
