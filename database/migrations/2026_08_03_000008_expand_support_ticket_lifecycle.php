<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('category', 40)->default('general')->after('requested_role');
            $table->string('priority', 20)->default('normal')->after('category');
            $table->timestamp('last_activity_at')->nullable()->after('closed_at')->index();
            $table->unsignedInteger('owner_unread_count')->default(0)->after('last_activity_at');
            $table->unsignedInteger('support_unread_count')->default(0)->after('owner_unread_count');
        });

        DB::table('support_tickets')->whereNull('last_activity_at')->update([
            'last_activity_at' => DB::raw('COALESCE(updated_at, created_at)'),
        ]);

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('sender_role', 20);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('message');
            $table->timestamps();
            $table->index(['support_ticket_id', 'created_at']);
        });

        Schema::create('support_ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('actor_role', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['support_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_events');
        Schema::dropIfExists('support_ticket_messages');
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn([
                'category', 'priority', 'last_activity_at',
                'owner_unread_count', 'support_unread_count',
            ]);
        });
    }
};
