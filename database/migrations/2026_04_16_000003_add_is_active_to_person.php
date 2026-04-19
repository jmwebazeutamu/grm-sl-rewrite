<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('person') && ! Schema::hasColumn('person', 'is_active')) {
            Schema::table('person', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->after('organization_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('person') && Schema::hasColumn('person', 'is_active')) {
            Schema::table('person', function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};
