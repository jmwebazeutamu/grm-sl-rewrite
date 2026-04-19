<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `person.email` nullable. Required by the direct-create user path —
 * some SL field-office staff don't have email addresses, and the admin may
 * set a password directly instead of sending a reset link.
 *
 * Laravel 11 supports `->change()` on SQLite/MySQL/Postgres natively; no
 * doctrine/dbal needed. The unique index is preserved because `change()`
 * alters the column in place without rebuilding the index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('person')) {
            return;
        }

        Schema::table('person', function (Blueprint $table): void {
            $table->string('email', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        // No-op. Reverting would break rows where email is null
        // (direct-created users).
    }
};
