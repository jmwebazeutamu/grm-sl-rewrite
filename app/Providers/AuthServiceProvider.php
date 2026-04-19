<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Audit\Models\AuditEntry;
use App\Domain\Audit\Policies\AuditEntryPolicy;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Policies\GrievancePolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Policies\RolePolicy;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Policies\LocalityPolicy;
use App\Domain\Organization\Models\Employee;
use App\Domain\Organization\Models\Office;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Organization\Policies\EmployeePolicy;
use App\Domain\Organization\Policies\OfficePolicy;
use App\Domain\Organization\Policies\OrganizationPolicy;
use App\Domain\Organization\Policies\ProgrammePolicy;
use App\Domain\Reference\Models\ActionType;
use App\Domain\Reference\Models\Area;
use App\Domain\Reference\Models\CaseConcept;
use App\Domain\Reference\Models\FeedbackStatus;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reference\Models\HowReported;
use App\Domain\Reference\Models\Priority;
use App\Domain\Reference\Models\ReviewOutcome;
use App\Domain\Reference\Models\Satisfaction;
use App\Domain\Reference\Models\Status;
use App\Domain\Reference\Policies\ActionTypePolicy;
use App\Domain\Reference\Policies\AreaPolicy;
use App\Domain\Reference\Policies\CaseConceptPolicy;
use App\Domain\Reference\Policies\FeedbackStatusPolicy;
use App\Domain\Reference\Policies\GrievanceTypePolicy;
use App\Domain\Reference\Policies\HowReportedPolicy;
use App\Domain\Reference\Policies\PriorityPolicy;
use App\Domain\Reference\Policies\ReviewOutcomePolicy;
use App\Domain\Reference\Policies\SatisfactionPolicy;
use App\Domain\Reference\Policies\StatusPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        // Grievance
        Grievance::class => GrievancePolicy::class,

        // Locality
        Locality::class => LocalityPolicy::class,

        // Reference (lookups)
        GrievanceType::class => GrievanceTypePolicy::class,
        HowReported::class => HowReportedPolicy::class,
        Priority::class => PriorityPolicy::class,
        Status::class => StatusPolicy::class,
        ActionType::class => ActionTypePolicy::class,
        Area::class => AreaPolicy::class,
        CaseConcept::class => CaseConceptPolicy::class,
        ReviewOutcome::class => ReviewOutcomePolicy::class,
        Satisfaction::class => SatisfactionPolicy::class,
        FeedbackStatus::class => FeedbackStatusPolicy::class,

        // Organization
        Organization::class => OrganizationPolicy::class,
        Office::class => OfficePolicy::class,
        Employee::class => EmployeePolicy::class,
        Programme::class => ProgrammePolicy::class,

        // Identity
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,

        // Audit
        AuditEntry::class => AuditEntryPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(fn (User $user) => $user->hasRole('super-admin') ? true : null);
    }
}
