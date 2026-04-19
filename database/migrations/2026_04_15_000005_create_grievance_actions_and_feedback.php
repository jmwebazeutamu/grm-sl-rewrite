<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unified action timeline. Replaces three legacy tables:
        // grievance_action_taken, grievance_remark, grievance_resolution.
        // Type discriminates investigation / contact / update / resolve / escalate.
        Schema::create('grievance_action', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grievance_id')->constrained('grievance')->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->text('body');
            $table->foreignId('assigned_to_id')->nullable()->constrained('person')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('person')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('person')->nullOnDelete();
            $table->timestamps();
            $table->index(['grievance_id', 'created_at']);
        });

        // Assignment: who's currently responsible. Kept as a column on the
        // grievance rather than a separate table — a case has at most one
        // officer working it at a time. History is in grievance_action rows
        // where type=update and body notes the reassignment.
        Schema::table('grievance', function (Blueprint $table): void {
            $table->foreignId('assigned_officer_id')->nullable()->after('reviewed_by_id')
                ->constrained('person')->nullOnDelete();
            // Classification fields moved onto the grievance itself per the
            // Phase 4 simplification — no more classification-as-state-gate.
            $table->foreignId('classified_organization_id')->nullable()->after('priority_id')
                ->constrained('organization')->nullOnDelete();
            $table->foreignId('classified_programme_id')->nullable()->after('classified_organization_id')
                ->constrained('programme')->nullOnDelete();
            $table->foreignId('classified_case_concept_id')->nullable()->after('classified_programme_id')
                ->constrained('case_concept')->nullOnDelete();
            $table->foreignId('classified_area_id')->nullable()->after('classified_case_concept_id')
                ->constrained('area')->nullOnDelete();
            $table->foreignId('access_id')->nullable()->after('classified_area_id')
                ->constrained('access')->nullOnDelete();
        });

        Schema::create('grievance_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grievance_id')->constrained('grievance')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('channel', 20)->default('web'); // web | sms | ivr
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index('grievance_id');
        });

        // Token for tokenised public feedback link. One-shot: consumed_at
        // prevents replay. Signed URL carries the token + expiry.
        Schema::create('grievance_feedback_token', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grievance_id')->constrained('grievance')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['grievance_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grievance_feedback_token');
        Schema::dropIfExists('grievance_feedback');
        Schema::table('grievance', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_officer_id');
            $table->dropConstrainedForeignId('classified_organization_id');
            $table->dropConstrainedForeignId('classified_programme_id');
            $table->dropConstrainedForeignId('classified_case_concept_id');
            $table->dropConstrainedForeignId('classified_area_id');
            $table->dropConstrainedForeignId('access_id');
        });
        Schema::dropIfExists('grievance_action');
    }
};
