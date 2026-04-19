<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** @var list<string> */
    private const RESOURCES = [
        // Grievance domain (Phase 3)
        'grievance',
        'action_taken',
        'resolution',
        'feedback',
        'remark',
        'classification',

        // Reference lookups
        'grievance_type',
        'how_reported',
        'priority',
        'status',
        'action_type',
        'area',
        'case_concept',
        'review_outcome',
        'satisfaction',
        'feedback_status',

        // Organization
        'organization',
        'office',
        'employee',
        'programme',

        // Geography
        'locality',
        'section',
        'chiefdom',
        'district',
        'region',
        'country',

        // Identity
        'user',
        'role',
        'permission',

        // Audit
        'audit',
    ];

    /** @var list<string> */
    private const ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete'];

    /**
     * Abilities beyond the standard five, keyed by resource.
     *
     * @var array<string, list<string>>
     */
    private const EXTRA_ABILITIES = [
        'grievance' => ['transition', 'review', 'view_pii', 'assign'],
        'user' => ['assign_roles'],
    ];

    /** @var list<string> */
    private const GRIEVANCE_RESOURCES = [
        'grievance', 'action_taken', 'resolution', 'feedback', 'remark', 'classification',
    ];

    /** @var list<string> */
    private const REFERENCE_RESOURCES = [
        'grievance_type', 'how_reported', 'priority', 'status',
        'action_type', 'area', 'case_concept', 'review_outcome',
        'satisfaction', 'feedback_status',
    ];

    /** @var list<string> */
    private const ORGANIZATION_RESOURCES = [
        'organization', 'office', 'employee', 'programme',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::RESOURCES as $resource) {
            foreach (self::ABILITIES as $ability) {
                Permission::findOrCreate("{$resource}.{$ability}");
            }

            foreach (self::EXTRA_ABILITIES[$resource] ?? [] as $ability) {
                Permission::findOrCreate("{$resource}.{$ability}");
            }
        }

        Role::findOrCreate('super-admin')->syncPermissions(Permission::all());

        // GRM officer: admin rights over their own org's grievances. The
        // policy scopes them to their organization_id. They're the
        // ones who assign/reassign cases and update classifications.
        Role::findOrCreate('grm-officer')->syncPermissions(
            Permission::whereIn('name', $this->expandAbilities(self::GRIEVANCE_RESOURCES, self::ABILITIES))
                ->orWhereIn('name', ['grievance.transition', 'grievance.review', 'grievance.view_pii', 'grievance.assign'])
                ->orWhereIn('name', $this->expandAbilities(self::REFERENCE_RESOURCES, ['viewAny', 'view']))
                ->orWhereIn('name', $this->expandAbilities(self::ORGANIZATION_RESOURCES, ['viewAny', 'view']))
                ->get(),
        );

        // Organization admin: manage own org + read grievance data.
        Role::findOrCreate('organization-admin')->syncPermissions(
            Permission::whereIn('name', $this->expandAbilities(self::ORGANIZATION_RESOURCES, self::ABILITIES))
                ->orWhereIn('name', $this->expandAbilities(['grievance'], ['viewAny', 'view']))
                ->get(),
        );

        // Reference admin: manage lookups.
        Role::findOrCreate('reference-admin')->syncPermissions(
            Permission::whereIn('name', $this->expandAbilities(self::REFERENCE_RESOURCES, self::ABILITIES))->get(),
        );

        // ACC reviewer: intake queue custodian. Holds view/review/transition/
        // update on grievances — the org-scoping policy additionally ensures
        // they only see the triage queue and their own org's classified cases.
        Role::findOrCreate('acc-reviewer')->syncPermissions(
            Permission::whereIn('name', [
                'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
                'grievance.review', 'grievance.transition', 'grievance.update',
                'grievance.assign',
            ])
                ->orWhereIn('name', $this->expandAbilities(self::REFERENCE_RESOURCES, ['viewAny', 'view']))
                ->orWhereIn('name', $this->expandAbilities(self::ORGANIZATION_RESOURCES, ['viewAny', 'view']))
                ->get(),
        );

        // Organization officer: works cases classified to their own org.
        // Cannot see/touch the intake queue. Cannot see other orgs' cases.
        Role::findOrCreate('organization-officer')->syncPermissions(
            Permission::whereIn('name', [
                'grievance.viewAny', 'grievance.view',
                'grievance.transition', 'grievance.update',
            ])
                ->orWhereIn('name', $this->expandAbilities(self::REFERENCE_RESOURCES, ['viewAny', 'view']))
                ->get(),
        );

        // Org-admin: delegated user-management + GRM-officer-equivalent grievance
        // rights, scoped by policy to own org. Cannot edit the organization
        // entity itself; cannot grant super-admin/acc-reviewer/org-admin roles.
        Role::findOrCreate('org-admin')->syncPermissions(
            Permission::whereIn('name', [
                'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
                'grievance.create', 'grievance.update',
                'grievance.transition', 'grievance.assign',
                'user.viewAny', 'user.view', 'user.create', 'user.update', 'user.assign_roles',
            ])
                ->orWhereIn('name', $this->expandAbilities(self::REFERENCE_RESOURCES, ['viewAny', 'view']))
                ->orWhereIn('name', $this->expandAbilities(self::ORGANIZATION_RESOURCES, ['viewAny', 'view']))
                ->get(),
        );

        // GRM Data Operator: system-level intake role identical to acc-reviewer.
        // Assigned to ACC org. Can accept/reject, categorize, close, escalate.
        Role::findOrCreate('grm-data-operator')->syncPermissions(
            Permission::whereIn('name', [
                'grievance.viewAny', 'grievance.view', 'grievance.view_pii',
                'grievance.review', 'grievance.transition', 'grievance.update',
                'grievance.assign',
            ])
                ->orWhereIn('name', $this->expandAbilities(self::REFERENCE_RESOURCES, ['viewAny', 'view']))
                ->orWhereIn('name', $this->expandAbilities(self::ORGANIZATION_RESOURCES, ['viewAny', 'view']))
                ->get(),
        );

        // Complainant: can only submit/view their own grievance (scoping done in policy).
        Role::findOrCreate('complainant');
    }

    /**
     * @param  list<string>  $resources
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private function expandAbilities(array $resources, array $abilities): array
    {
        $out = [];
        foreach ($resources as $resource) {
            foreach ($abilities as $ability) {
                $out[] = "{$resource}.{$ability}";
            }
        }

        return $out;
    }
}
