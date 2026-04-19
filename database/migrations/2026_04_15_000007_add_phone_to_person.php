<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds phone_number to the `person` table for SMS notification routing.
 *
 * The `person` table itself is created by the legacy-compatible person
 * migration (Phase 7 data-migration scaffolding) — this migration adds the
 * column idempotently so it's safe to run in any order after that.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('person') && ! Schema::hasColumn('person', 'phone_number')) {
            Schema::table('person', function (Blueprint $table): void {
                $table->string('phone_number', 30)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('person') && Schema::hasColumn('person', 'phone_number')) {
            Schema::table('person', function (Blueprint $table): void {
                $table->dropColumn('phone_number');
            });
        }
    }
};
