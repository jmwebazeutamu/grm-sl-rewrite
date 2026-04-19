<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel's standard notifications table — for the database channel.
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index('read_at');
        });

        // Per-user channel toggles. Defaulting to all-on — the user opts
        // out, they don't opt in.
        Schema::create('notification_preference', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained('person')->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->timestamps();
        });

        // SMS outbox — provider-accepted messages, so operators can see
        // what went out and why certain recipients never got a delivery
        // receipt. Kept minimal — not a replacement for provider analytics.
        Schema::create('sms_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('to', 30);
            $table->text('body');
            $table->string('provider_message_id', 100)->nullable();
            $table->string('status', 20)->default('sent'); // sent | failed
            $table->text('failure_reason')->nullable();
            $table->timestamp('dispatched_at');
            $table->timestamps();
            $table->index('to');
            $table->index('dispatched_at');
        });

        // Inbound SMS log — every message received, parsed or not. The
        // STATUS lookup is the only command today; if we add more (e.g.
        // "HELP", language switch) their handlers can read from here for
        // auditing.
        Schema::create('sms_inbox', function (Blueprint $table): void {
            $table->id();
            $table->string('from', 30);
            $table->text('body');
            $table->string('provider_message_id', 100)->nullable();
            $table->string('parsed_command', 50)->nullable();
            $table->foreignId('matched_grievance_id')->nullable()->constrained('grievance')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index('from');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_inbox');
        Schema::dropIfExists('sms_outbox');
        Schema::dropIfExists('notification_preference');
        Schema::dropIfExists('notifications');
    }
};
