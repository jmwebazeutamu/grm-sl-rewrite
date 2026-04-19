<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grievance')) {
            return;
        }

        Schema::table('grievance', function (Blueprint $table): void {
            if (! Schema::hasColumn('grievance', 'closure_comment')) {
                $table->text('closure_comment')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('grievance', 'closure_reviewed_by_id')) {
                $table->unsignedBigInteger('closure_reviewed_by_id')->nullable()->after('closure_comment');
                $table->foreign('closure_reviewed_by_id')->references('id')->on('person')->nullOnDelete();
            }
            if (! Schema::hasColumn('grievance', 'closure_reviewed_at')) {
                $table->timestamp('closure_reviewed_at')->nullable()->after('closure_reviewed_by_id');
            }
            if (! Schema::hasColumn('grievance', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('closure_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('grievance')) {
            return;
        }

        Schema::table('grievance', function (Blueprint $table): void {
            if (Schema::hasColumn('grievance', 'closure_reviewed_by_id')) {
                try {
                    $table->dropForeign(['closure_reviewed_by_id']);
                } catch (\Throwable) {
                    // sqlite / already dropped
                }
            }
            foreach (['closure_comment', 'closure_reviewed_by_id', 'closure_reviewed_at', 'reopened_at'] as $col) {
                if (Schema::hasColumn('grievance', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
