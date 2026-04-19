<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grievance_suspect')) {
            Schema::table('grievance_suspect', function (Blueprint $table): void {
                if (! Schema::hasColumn('grievance_suspect', 'is_beneficiary')) {
                    $table->boolean('is_beneficiary')->default(false)->after('address');
                }
                if (! Schema::hasColumn('grievance_suspect', 'programme_id')) {
                    $table->unsignedBigInteger('programme_id')->nullable()->after('is_beneficiary');
                    $table->foreign('programme_id')->references('id')->on('programme')->nullOnDelete();
                }
                if (! Schema::hasColumn('grievance_suspect', 'implementing_organization_id')) {
                    $table->unsignedBigInteger('implementing_organization_id')->nullable()->after('programme_id');
                    $table->foreign('implementing_organization_id')->references('id')->on('organization')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('programme') && ! Schema::hasColumn('programme', 'active')) {
            Schema::table('programme', function (Blueprint $table): void {
                $table->boolean('active')->default(true)->after('organization_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('grievance_suspect')) {
            Schema::table('grievance_suspect', function (Blueprint $table): void {
                foreach (['implementing_organization_id', 'programme_id'] as $col) {
                    if (Schema::hasColumn('grievance_suspect', $col)) {
                        try {
                            $table->dropForeign([$col]);
                        } catch (\Throwable) {
                            // sqlite / already dropped
                        }
                    }
                }
                foreach (['is_beneficiary', 'programme_id', 'implementing_organization_id'] as $col) {
                    if (Schema::hasColumn('grievance_suspect', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('programme') && Schema::hasColumn('programme', 'active')) {
            Schema::table('programme', function (Blueprint $table): void {
                $table->dropColumn('active');
            });
        }
    }
};
