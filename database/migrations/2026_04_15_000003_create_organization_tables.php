<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200);
            $table->string('acronym', 50)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('organization')->nullOnDelete();
            $this->authorship($table);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['parent_id', 'name']);
        });

        Schema::create('office', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200);
            $table->string('acronym', 50)->nullable();
            $table->string('address', 500)->nullable();
            $table->boolean('is_headquarters')->default(false);
            $table->foreignId('organization_id')->constrained('organization')->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('region')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('district')->nullOnDelete();
            $this->authorship($table);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'name']);
        });

        Schema::create('office_person', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_id')->constrained('office')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('person')->cascadeOnDelete();
            $this->authorship($table);
            $table->timestamps();
            $table->unique(['office_id', 'person_id']);
        });

        Schema::create('employee', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->nullable();
            $table->string('mobile_number', 30)->nullable();
            $table->string('office_number', 30)->nullable();
            $table->foreignId('organization_id')->constrained('organization')->cascadeOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('office')->nullOnDelete();
            $this->authorship($table);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'last_name']);
        });

        Schema::create('programme', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200);
            $table->string('acronym', 50)->nullable();
            $table->string('code', 50)->nullable();
            $table->foreignId('organization_id')->constrained('organization')->cascadeOnDelete();
            $this->authorship($table);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('organization_grievance_type', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organization')->cascadeOnDelete();
            $table->foreignId('grievance_type_id')->constrained('grievance_type')->cascadeOnDelete();
            $this->authorship($table);
            $table->timestamps();
            $table->unique(['organization_id', 'grievance_type_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'organization_grievance_type', 'programme', 'employee',
            'office_person', 'office', 'organization',
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
