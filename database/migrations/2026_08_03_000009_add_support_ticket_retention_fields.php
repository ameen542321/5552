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
            $table->timestamp('expires_at')->nullable()->after('last_activity_at')->index();
            $table->timestamp('cancelled_at')->nullable()->after('expires_at');
            $table->string('cancel_reason')->nullable()->after('cancelled_at');
        });
        Schema::table('support_sessions', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('started_at')->index();
        });
        DB::table('support_sessions')->whereNull('expires_at')->orderBy('id')->chunkById(500, function ($sessions): void {
            foreach ($sessions as $session) {
                DB::table('support_sessions')->where('id', $session->id)->update([
                    'expires_at' => \Carbon\Carbon::parse($session->started_at)->addHours(4),
                ]);
            }
        });

        DB::table('support_tickets')
            ->whereNull('expires_at')
            ->whereNull('responded_at')
            ->orderBy('id')
            ->chunkById(500, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    DB::table('support_tickets')->where('id', $ticket->id)->update([
                        'expires_at' => \Carbon\Carbon::parse($ticket->created_at)->addDays(7),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('support_sessions', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['expires_at', 'cancelled_at', 'cancel_reason']);
        });
    }
};
