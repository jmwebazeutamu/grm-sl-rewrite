<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('programme') && ! Schema::hasColumn('programme', 'status')) {
            Schema::table('programme', function (Blueprint $table): void {
                $table->string('status', 20)->default('active')->after('organization_id');
            });

            // Back-fill status from the legacy boolean `active` column.
            if (Schema::hasColumn('programme', 'active')) {
                DB::table('programme')->where('active', false)->update(['status' => 'closed']);
                DB::table('programme')->where('active', true)->update(['status' => 'active']);
            }
        }

        if (Schema::hasTable('grievance')) {
            Schema::table('grievance', function (Blueprint $table): void {
                if (! Schema::hasColumn('grievance', 'implementing_organization_id')) {
                    $table->unsignedBigInteger('implementing_organization_id')->nullable()->after('classified_area_id');
                    $table->foreign('implementing_organization_id')->references('id')->on('organization')->nullOnDelete();
                }
                if (! Schema::hasColumn('grievance', 'programme_id')) {
                    $table->unsignedBigInteger('programme_id')->nullable()->after('implementing_organization_id');
                    $table->foreign('programme_id')->references('id')->on('programme')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('grievance')) {
            Schema::table('grievance', function (Blueprint $table): void {
                foreach (['implementing_organization_id', 'programme_id'] as $col) {
                    if (Schema::hasColumn('grievance', $col)) {
                        try {
                            $table->dropForeign([$col]);
                        } catch (\Throwable) {
                            // sqlite
                        }
                    }
                }
                foreach (['implementing_organization_id', 'programme_id'] as $col) {
                    if (Schema::hasColumn('grievance', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('programme') && Schema::hasColumn('programme', 'status')) {
            Schema::table('programme', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
    }
};
