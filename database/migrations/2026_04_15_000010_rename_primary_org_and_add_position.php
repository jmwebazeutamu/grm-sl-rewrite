<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renames `person.primary_organization_id` to `person.organization_id` (the
 * canonical org-membership FK) and adds `person.position` to hold the merged
 * job-title column from the `employee` directory.
 *
 * Kept nullable. Deviates from the feature-prompt's "NOT NULL" because
 * GrievancePolicy::isSupervisor() relies on a null value to identify
 * cross-org head-office supervisors. Making it NOT NULL would require
 * inventing a synthetic "Head Office" org; that's a separate decision.
 *
 * The `employee` table is NOT dropped — it remains as a contact directory.
 * Removal is a follow-up task.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('person')) {
            return;
        }

        // Rename primary_organization_id → organization_id.
        if (
            Schema::hasColumn('person', 'primary_organization_id')
            && ! Schema::hasColumn('person', 'organization_id')
        ) {
            // Drop the FK first so the column can be renamed on SQLite.
            Schema::table('person', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['primary_organization_id']);
                } catch (\Throwable) {
                    // SQLite may report no constraint; ignore.
                }
                $table->renameColumn('primary_organization_id', 'organization_id');
            });

            // Reinstate the FK under the new column name.
            Schema::table('person', function (Blueprint $table): void {
                $table->foreign('organization_id')
                    ->references('id')->on('organization')
                    ->nullOnDelete();
            });
        }

        // Add position column (merged from employee.position — employee table
        // doesn't actually have one yet, but the User-facing concept fits here).
        if (! Schema::hasColumn('person', 'position')) {
            Schema::table('person', function (Blueprint $table): void {
                $table->string('position', 150)->nullable()->after('phone_number');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('person')) {
            return;
        }

        if (Schema::hasColumn('person', 'position')) {
            Schema::table('person', function (Blueprint $table): void {
                $table->dropColumn('position');
            });
        }

        if (
            Schema::hasColumn('person', 'organization_id')
            && ! Schema::hasColumn('person', 'primary_organization_id')
        ) {
            Schema::table('person', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['organization_id']);
                } catch (\Throwable) {
                }
                $table->renameColumn('organization_id', 'primary_organization_id');
            });
            Schema::table('person', function (Blueprint $table): void {
                $table->foreign('primary_organization_id')
                    ->references('id')->on('organization')
                    ->nullOnDelete();
            });
        }
    }
};
