<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grievance_attachment')) {
            return;
        }

        Schema::table('grievance_attachment', function (Blueprint $table): void {
            if (! Schema::hasColumn('grievance_attachment', 'source')) {
                $table->string('source', 20)->default('submission')->after('uploaded_by_id');
            }
            if (! Schema::hasColumn('grievance_attachment', 'description')) {
                $table->text('description')->nullable()->after('source');
            }
            if (! Schema::hasColumn('grievance_attachment', 'stored_filename')) {
                $table->string('stored_filename', 100)->nullable()->after('original_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('grievance_attachment')) {
            return;
        }

        Schema::table('grievance_attachment', function (Blueprint $table): void {
            foreach (['source', 'description', 'stored_filename'] as $col) {
                if (Schema::hasColumn('grievance_attachment', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
