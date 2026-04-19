<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grievance_type', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $this->authorship($table);
            $table->timestamps();
        });

        Schema::create('how_reported', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $this->authorship($table);
            $table->timestamps();
        });

        Schema::create('priority', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('ranking')->nullable();
            $table->unsignedInteger('response_time_hours')->nullable();
            $table->unsignedInteger('resolution_time_hours')->nullable();
            $this->authorship($table);
            $table->timestamps();
        });

        Schema::create('status', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->string('category_type', 50)->nullable();
            $this->authorship($table);
            $table->timestamps();
        });

        Schema::create('action_type', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $this->authorship($table);
            $table->timestamps();
        });

        Schema::create('area', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $this->authorship($table);
            $table->timestamps();
        });

        Schema::create('case_concept', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('grievance_type_id')->constrained('grievance_type')->restrictOnDelete();
            $this->authorship($table);
            $table->timestamps();
            $table->unique(['grievance_type_id', 'name']);
        });

        Schema::create('review_outcome', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $this->authorship($table);
            $table->timestamps();
        });

        Schema::create('satisfaction', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $this->authorship($table);
            $table->timestamps();
        });

        Schema::create('feedback_status', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $this->authorship($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'feedback_status', 'satisfaction', 'review_outcome', 'case_concept',
            'area', 'action_type', 'status', 'priority', 'how_reported', 'grievance_type',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function authorship(Blueprint $table): void
    {
        $table->foreignId('created_by_id')->nullable()->constrained('person')->nullOnDelete();
        $table->foreignId('updated_by_id')->nullable()->constrained('person')->nullOnDelete();
    }
};
