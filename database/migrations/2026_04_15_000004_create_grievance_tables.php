<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $this->authorship($table);
            $table->timestamps();
        });

        Schema::create('grievance', function (Blueprint $table): void {
            $table->id();
            $table->string('g_number', 30)->unique();

            // Narrative
            $table->text('summary');
            $table->text('description')->nullable();

            // Classification (intake)
            $table->foreignId('grievance_type_id')->constrained('grievance_type')->restrictOnDelete();
            $table->foreignId('how_reported_id')->nullable()->constrained('how_reported')->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained('priority')->nullOnDelete();

            // State (explicit — no more inference from row counts)
            $table->string('state', 30)->default('submitted')->index();

            // Intake anonymity flag — when true, complainer's identity is
            // still stored but masked from non-super-admin viewers.
            $table->boolean('is_anonymous')->default(false);

            // Review
            $table->text('review_comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('person')->nullOnDelete();

            // Location
            $table->foreignId('region_id')->nullable()->constrained('region')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('district')->nullOnDelete();
            $table->foreignId('chiefdom_id')->nullable()->constrained('chiefdom')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('section')->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained('locality')->nullOnDelete();

            // Lifecycle timestamps
            $table->timestamp('received_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Self-reference for linked cases
            $table->foreignId('related_grievance_id')->nullable()->constrained('grievance')->nullOnDelete();

            $this->authorship($table);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['state', 'received_at']);
        });

        Schema::create('grievance_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grievance_id')->constrained('grievance')->cascadeOnDelete();
            $table->string('from_state', 30)->nullable();
            $table->string('to_state', 30);
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('person')->nullOnDelete();
            $table->timestamp('occurred_at');

            $table->index(['grievance_id', 'occurred_at']);
        });

        Schema::create('grievance_complainer', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grievance_id')->constrained('grievance')->cascadeOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organization')->nullOnDelete();
            $table->string('other_organization', 200)->nullable();
            $table->foreignId('region_id')->nullable()->constrained('region')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('district')->nullOnDelete();
            $table->foreignId('chiefdom_id')->nullable()->constrained('chiefdom')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('section')->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained('locality')->nullOnDelete();
            $table->timestamps();
            $table->index('grievance_id');
        });

        Schema::create('grievance_suspect', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grievance_id')->constrained('grievance')->cascadeOnDelete();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('title', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organization')->nullOnDelete();
            $table->string('other_organization', 200)->nullable();
            $table->foreignId('region_id')->nullable()->constrained('region')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('district')->nullOnDelete();
            $table->foreignId('chiefdom_id')->nullable()->constrained('chiefdom')->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('section')->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained('locality')->nullOnDelete();
            $table->timestamps();
            $table->index('grievance_id');
        });

        Schema::create('grievance_beneficiary', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grievance_id')->constrained('grievance')->cascadeOnDelete();
            $table->string('name', 200)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('household_id', 50)->nullable();
            $table->foreignId('implementing_agency_id')->nullable()->constrained('organization')->nullOnDelete();
            $table->foreignId('social_programme_id')->nullable()->constrained('programme')->nullOnDelete();
            $table->timestamps();
            $table->index('grievance_id');
        });

        Schema::create('grievance_classification', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grievance_id')->constrained('grievance')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organization')->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained('programme')->nullOnDelete();
            $table->foreignId('case_concept_id')->nullable()->constrained('case_concept')->nullOnDelete();
            $table->foreignId('responsible_area_id')->nullable()->constrained('area')->nullOnDelete();
            $table->foreignId('access_id')->nullable()->constrained('access')->nullOnDelete();
            $this->authorship($table);
            $table->timestamps();
            $table->index('grievance_id');
        });

        Schema::create('grievance_attachment', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grievance_id')->constrained('grievance')->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by_id')->nullable()->constrained('person')->nullOnDelete();
            $table->timestamps();
            $table->index('grievance_id');
        });
    }

    public function down(): void
    {
        foreach ([
            'grievance_attachment', 'grievance_classification',
            'grievance_beneficiary', 'grievance_suspect', 'grievance_complainer',
            'grievance_status_history', 'grievance', 'access',
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
