<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->foreignId('verified_by')->nullable()->after('acknowledged_by')->constrained('users')->nullOnDelete();
            $table->text('verification_note')->nullable()->after('resolution');
            $table->dateTime('verified_at')->nullable()->after('resolved_at');
            $table->string('response_action', 40)->nullable()->after('verification_note');
            $table->dateTime('response_expires_at')->nullable()->after('response_action');
        });
    }

    public function down(): void
    {
        Schema::table('security_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verification_note', 'verified_at', 'response_action', 'response_expires_at']);
        });
    }
};
