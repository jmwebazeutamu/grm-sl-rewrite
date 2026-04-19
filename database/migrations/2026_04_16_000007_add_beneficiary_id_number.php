<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grievance_suspect') && ! Schema::hasColumn('grievance_suspect', 'beneficiary_id_number')) {
            Schema::table('grievance_suspect', function (Blueprint $table): void {
                $table->string('beneficiary_id_number', 100)->nullable()->after('implementing_organization_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('grievance_suspect') && Schema::hasColumn('grievance_suspect', 'beneficiary_id_number')) {
            Schema::table('grievance_suspect', function (Blueprint $table): void {
                $table->dropColumn('beneficiary_id_number');
            });
        }
    }
};
