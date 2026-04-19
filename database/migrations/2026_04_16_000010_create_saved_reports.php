<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saved_reports')) {
            Schema::create('saved_reports', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 200);
                $table->foreignId('created_by_id')->constrained('person')->cascadeOnDelete();
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->foreign('organization_id')->references('id')->on('organization')->nullOnDelete();
                $table->json('fields');
                $table->json('filters')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
