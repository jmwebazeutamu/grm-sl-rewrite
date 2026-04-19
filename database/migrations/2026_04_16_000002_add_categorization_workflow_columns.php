<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the categorization pipeline columns to `grievance` and creates the
 * per-org grievance types table.
 *
 * New states in the pipeline: accepted → categorized → assigned →
 * org_classified → in_progress. Each stage gates on specific fields
 * being set before the transition is allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pipeline columns on grievance.
        Schema::table('grievance', function (Blueprint $table): void {
            $table->string('category', 20)->nullable()->after('state');
            $table->timestamp('accepted_at')->nullable()->after('received_at');
            $table->timestamp('categorized_at')->nullable()->after('accepted_at');
            $table->timestamp('assigned_at')->nullable()->after('categorized_at');
        });

        // Per-org grievance sub-classification types.
        Schema::create('org_grievance_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organization')->cascadeOnDelete();
            $table->string('label', 150);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'label']);
        });

        // FK from grievance → org_grievance_types.
        Schema::table('grievance', function (Blueprint $table): void {
            $table->foreignId('org_classification_id')
                ->nullable()
                ->after('classified_area_id')
                ->constrained('org_grievance_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grievance', function (Blueprint $table): void {
            try {
                $table->dropForeign(['org_classification_id']);
            } catch (\Throwable) {
            }
            $table->dropColumn(['category', 'accepted_at', 'categorized_at', 'assigned_at', 'org_classification_id']);
        });

        Schema::dropIfExists('org_grievance_types');
    }
};
