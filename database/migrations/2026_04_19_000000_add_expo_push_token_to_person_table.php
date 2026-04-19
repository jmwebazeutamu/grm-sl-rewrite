<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person', function (Blueprint $table): void {
            $table->string('expo_push_token')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('person', function (Blueprint $table): void {
            $table->dropColumn('expo_push_token');
        });
    }
};
