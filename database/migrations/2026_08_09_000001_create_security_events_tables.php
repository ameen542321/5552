<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code', 80)->index();
            $table->string('category', 40)->index();
            $table->string('severity', 20)->index();
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->string('status', 30)->default('new')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source_ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('route')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->nullableMorphs('actor');
            $table->nullableMorphs('target');
            $table->string('fingerprint', 64)->index();
            $table->unsignedInteger('occurrences')->default(1);
            $table->json('evidence')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            // DATETIME متوافق مع إصدارات MySQL/MariaDB التي ترفض عدة أعمدة TIMESTAMP إلزامية بلا قيم افتراضية.
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->dateTime('detected_at');
            $table->dateTime('acknowledged_at')->nullable();
            $table->dateTime('contained_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['fingerprint', 'status', 'last_seen_at'], 'security_events_grouping_index');
        });

        Schema::create('security_event_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_event_activities');
        Schema::dropIfExists('security_events');
    }
};
