<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_sessions', function (Blueprint $table) {
            $table->string('admin_name')->nullable()->after('admin_id');
            $table->string('admin_email')->nullable()->after('admin_name');
            $table->string('target_name')->nullable()->after('target_id');
            $table->string('target_role')->nullable()->after('target_name');
            $table->string('ticket_reference')->nullable()->after('reason')->index();
        });
    }

    public function down(): void
    {
        Schema::table('support_sessions', function (Blueprint $table) {
            $table->dropIndex(['ticket_reference']);
            $table->dropColumn([
                'admin_name', 'admin_email', 'target_name', 'target_role', 'ticket_reference',
            ]);
        });
    }
};
