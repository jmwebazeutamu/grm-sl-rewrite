<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('programme') && ! Schema::hasColumn('programme', 'sla_days')) {
            Schema::table('programme', function (Blueprint $table): void {
                $table->unsignedInteger('sla_days')->nullable()->after('status');
            });
        }

        if (Schema::hasTable('organization') && ! Schema::hasColumn('organization', 'sla_days')) {
            Schema::table('organization', function (Blueprint $table): void {
                $table->unsignedInteger('sla_days')->default(30)->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('programme') && Schema::hasColumn('programme', 'sla_days')) {
            Schema::table('programme', function (Blueprint $table): void {
                $table->dropColumn('sla_days');
            });
        }
        if (Schema::hasTable('organization') && Schema::hasColumn('organization', 'sla_days')) {
            Schema::table('organization', function (Blueprint $table): void {
                $table->dropColumn('sla_days');
            });
        }
    }
};
