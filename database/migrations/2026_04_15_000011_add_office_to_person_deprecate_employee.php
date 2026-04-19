<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merges the remaining employee-only column onto `person`. After this
 * migration, the `person` table is the single source of truth for staff
 * identity + org + office + role. The `employee` table is no longer used
 * for staff creation — the "Invite user" flow owns that responsibility.
 *
 * DEPRECATED: `employee` table. Kept intact for this migration. Removal,
 * and any data-migration of legacy employee rows that have no matching
 * user account, is a separate task.
 *
 * Columns present on `employee` but NOT merged onto `person`:
 *   - first_name/last_name — person.name (single column) is the canonical
 *     display; splitting would reverse a simplification.
 *   - office_number — desk/landline; not individual-staff data we need here
 *     (offices already have their own contact metadata).
 *   - authorship columns — already present on person via RecordsAuthorship.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('person')) {
            return;
        }

        if (! Schema::hasColumn('person', 'office_id')) {
            Schema::table('person', function (Blueprint $table): void {
                $table->foreignId('office_id')
                    ->nullable()
                    ->after('organization_id')
                    ->constrained('office')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('person')) {
            return;
        }

        if (Schema::hasColumn('person', 'office_id')) {
            Schema::table('person', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['office_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('office_id');
            });
        }
    }
};
