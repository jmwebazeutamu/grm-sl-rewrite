<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Preserves legacy table names from the GRM app (`country`, `region`, etc).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('iso_code', 3)->nullable();
            $table->timestamps();
        });

        Schema::create('region', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('country_id')->constrained('country')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['country_id', 'name']);
        });

        Schema::create('district', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('region_id')->constrained('region')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['region_id', 'name']);
        });

        Schema::create('chiefdom', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('district_id')->constrained('district')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['district_id', 'name']);
        });

        Schema::create('section', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('chiefdom_id')->constrained('chiefdom')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['chiefdom_id', 'name']);
        });

        Schema::create('locality', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('section_id')->constrained('section')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['section_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locality');
        Schema::dropIfExists('section');
        Schema::dropIfExists('chiefdom');
        Schema::dropIfExists('district');
        Schema::dropIfExists('region');
        Schema::dropIfExists('country');
    }
};
