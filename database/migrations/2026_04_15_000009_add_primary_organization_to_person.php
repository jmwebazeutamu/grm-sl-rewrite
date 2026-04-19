<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * primary_organization_id is the simplest possible multi-tenancy signal:
 * the organization an internal user belongs to. The `organization-admin`
 * role uses it to scope what they can see and mutate — see OrganizationPolicy
 * and EmployeePolicy.
 *
 * If/when we need richer multi-org assignments, the existing office_person
 * pivot is the richer home. Don't add complexity here until the requirement
 * is real.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('person') && ! Schema::hasColumn('person', 'primary_organization_id')) {
            Schema::table('person', function (Blueprint $table): void {
                $table->foreignId('primary_organization_id')
                    ->nullable()
                    ->after('phone_number')
                    ->constrained('organization')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('person') && Schema::hasColumn('person', 'primary_organization_id')) {
            Schema::table('person', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('primary_organization_id');
            });
        }
    }
};
