<?php

declare(strict_types=1);

use App\Domain\Audit\Http\Controllers\AuditController;
use App\Domain\Audit\Models\AuditEntry;
use App\Domain\Grievance\Http\Controllers\Admin\AcceptRejectController;
use App\Domain\Grievance\Http\Controllers\Admin\ActionController as AdminActionController;
use App\Domain\Grievance\Http\Controllers\Admin\AssignmentController as AdminAssignmentController;
use App\Domain\Grievance\Http\Controllers\Admin\AttachmentController as AdminAttachmentController;
use App\Domain\Grievance\Http\Controllers\Admin\BeneficiaryController as AdminBeneficiaryController;
use App\Domain\Grievance\Http\Controllers\Admin\ClosureController as AdminClosureController;
use App\Domain\Grievance\Http\Controllers\Admin\LocationController as AdminLocationController;
use App\Domain\Locality\Http\Controllers\Admin\GeographyController as AdminGeographyController;
use App\Domain\Locality\Http\Controllers\GeographyApiController;
use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Region;
use App\Domain\Locality\Models\Section;
use App\Domain\Organization\Http\Controllers\Admin\ProgrammeController as AdminProgrammeController;
use App\Domain\Organization\Http\Controllers\Admin\SlaSettingsController;
use App\Domain\Organization\Models\Programme;
use App\Domain\Grievance\Http\Controllers\Admin\CategorizationController;
use App\Domain\Grievance\Http\Controllers\Admin\GrievanceController as AdminGrievanceController;
use App\Domain\Grievance\Http\Controllers\Admin\OrgClassificationController;
use App\Domain\Grievance\Http\Controllers\Admin\OrgGrievanceTypeController;
use App\Domain\Grievance\Http\Controllers\FeedbackController;
use App\Domain\Grievance\Http\Controllers\PublicGrievanceController;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Http\Controllers\Admin\OrgUserController;
use App\Domain\Identity\Http\Controllers\RoleController;
use App\Domain\Identity\Http\Controllers\UserController;
use App\Domain\Identity\Models\User;
use App\Domain\Locality\Http\Controllers\LocalityController;
use App\Domain\Locality\Models\Locality;
use App\Domain\Notification\Http\Controllers\InboundSmsController;
use App\Domain\Notification\Http\Controllers\PreferencesController;
use App\Domain\Organization\Http\Controllers\OrganizationController;
use App\Domain\Organization\Models\Organization;
use App\Domain\Reference\Http\Controllers\ActionTypeController;
use App\Domain\Reference\Http\Controllers\AreaController;
use App\Domain\Reference\Http\Controllers\CaseConceptController;
use App\Domain\Reference\Http\Controllers\FeedbackStatusController;
use App\Domain\Reference\Http\Controllers\GrievanceTypeController;
use App\Domain\Reference\Http\Controllers\HowReportedController;
use App\Domain\Reference\Http\Controllers\PriorityController;
use App\Domain\Reference\Http\Controllers\ReviewOutcomeController;
use App\Domain\Reference\Http\Controllers\SatisfactionController;
use App\Domain\Reference\Http\Controllers\StatusController;
use App\Domain\Reference\Models\ActionType;
use App\Domain\Reference\Models\Area;
use App\Domain\Reference\Models\CaseConcept;
use App\Domain\Reference\Models\FeedbackStatus;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reference\Models\HowReported;
use App\Domain\Reference\Models\Priority;
use App\Domain\Reference\Models\ReviewOutcome;
use App\Domain\Reference\Models\Satisfaction;
use App\Domain\Reference\Models\Status as StatusModel;
use App\Domain\Reporting\Http\Controllers\QuarterlyReportController;
use App\Domain\Reporting\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Resourceful routes helper
|--------------------------------------------------------------------------
|
| Registers the seven standard resource routes with per-method policy
| middleware. We don't use `Route::resource()->middleware([...])` because
| that applies every value to every method — `can:view,{param}` ends up on
| routes with no such parameter and 403s. Explicit declarations avoid the
| trap.
|
| Super-admin always bypasses via Gate::before; regular users are checked
| against the model's registered Policy.
*/
if (! function_exists('resourceful')) {
    function resourceful(
        string $slug,
        string $controller,
        string $model,
        ?string $param = null,
        bool $bindExplicitly = false,
    ): void {
        $param ??= str_replace('-', '_', rtrim($slug, 's'));

        if ($bindExplicitly) {
            // Needed when the controller method signature uses a generic
            // `Model $m` rather than a concrete type — Laravel's implicit
            // binding can't resolve those on its own.
            Route::bind($param, fn ($id) => $model::findOrFail($id));
        }

        Route::get($slug, [$controller, 'index'])
            ->middleware('can:viewAny,'.$model)
            ->name($slug.'.index');

        Route::get($slug.'/create', [$controller, 'create'])
            ->middleware('can:create,'.$model)
            ->name($slug.'.create');

        Route::post($slug, [$controller, 'store'])
            ->middleware('can:create,'.$model)
            ->name($slug.'.store');

        Route::get($slug.'/{'.$param.'}', [$controller, 'show'])
            ->middleware('can:view,'.$param)
            ->name($slug.'.show');

        Route::get($slug.'/{'.$param.'}/edit', [$controller, 'edit'])
            ->middleware('can:update,'.$param)
            ->name($slug.'.edit');

        Route::put($slug.'/{'.$param.'}', [$controller, 'update'])
            ->middleware('can:update,'.$param)
            ->name($slug.'.update');

        Route::delete($slug.'/{'.$param.'}', [$controller, 'destroy'])
            ->middleware('can:delete,'.$param)
            ->name($slug.'.destroy');
    }
}

Route::get('/', fn () => Inertia::render('Home'))->name('home');

// Public grievance submission — no auth required; reCAPTCHA + rate limiter
// are the only gates. Status lookup by g_number is separate and unthrottled.
// Public geography API — used by the cascading LocationPicker (no auth).
Route::prefix('api/geography')->group(function (): void {
    Route::get('districts', [GeographyApiController::class, 'districts']);
    Route::get('chiefdoms', [GeographyApiController::class, 'chiefdoms']);
    Route::get('sections', [GeographyApiController::class, 'sections']);
    Route::get('localities', [GeographyApiController::class, 'localities']);
});

Route::middleware('throttle:grievance-submit')->group(function (): void {
    Route::get('/submit-grievance', [PublicGrievanceController::class, 'create'])->name('grievances.public.create');
    Route::post('/submit-grievance', [PublicGrievanceController::class, 'store'])->name('grievances.public.store');
});
Route::get('/grievance/{g_number}/confirmation', [PublicGrievanceController::class, 'confirmation'])
    ->name('grievances.public.confirmation');
Route::get('/grievance/{g_number}/status', [PublicGrievanceController::class, 'status'])
    ->name('grievances.public.status');
Route::match(['get', 'post'], '/grievance/status', [PublicGrievanceController::class, 'statusLookup'])
    ->middleware('throttle:10,1')
    ->name('grievances.public.statusLookup');

// Tokenised feedback — no auth. The token IS the credential (signed, one-shot).
Route::get('/feedback/{token}', [FeedbackController::class, 'show'])->name('grievances.feedback.show');
Route::post('/feedback/{token}', [FeedbackController::class, 'store'])->name('grievances.feedback.store');
Route::get('/feedback/thanks', [FeedbackController::class, 'thanks'])->name('grievances.feedback.thanks');

// Inbound SMS webhook from Africa's Talking. Shared-secret verified.
Route::post('/webhooks/sms/inbound', InboundSmsController::class)
    ->middleware('throttle:sms-inbound')
    ->name('webhooks.sms.inbound');

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::get('/dashboard', [ReportsController::class, 'dashboard'])->name('dashboard');

    // Self-service profile (not to be confused with admin user management).
    Route::get('/profile', function (\Illuminate\Http\Request $request) {
        $user = $request->user()->load('roles:id,name');

        return \Inertia\Inertia::render('Profile', [
            'user' => $user->only(['id', 'username', 'name', 'email', 'email_verified_at', 'phone_number']),
            'roles' => $user->roles->pluck('name'),
            'permissionCount' => $user->getAllPermissions()->count(),
        ]);
    })->name('profile.show');

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('notifications', [PreferencesController::class, 'edit'])->name('notifications.edit');
        Route::put('notifications', [PreferencesController::class, 'update'])->name('notifications.update');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        resourceful('localities', LocalityController::class, Locality::class);
        resourceful('organizations', OrganizationController::class, Organization::class);

        // Grievance routes are partially resourceful (index/show only) plus
        // several bespoke actions.
        Route::get('grievances', [AdminGrievanceController::class, 'index'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('grievances.index');
        Route::get('beneficiaries', [AdminBeneficiaryController::class, 'index'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('beneficiaries.index');
        Route::get('grievances/{grievance}', [AdminGrievanceController::class, 'show'])
            ->middleware('can:view,grievance')
            ->name('grievances.show');

        Route::post('grievances/{grievance}/transition', [AdminGrievanceController::class, 'transition'])
            ->middleware('can:transition,grievance')
            ->name('grievances.transition');

        Route::post('grievances/{grievance}/actions', [AdminActionController::class, 'store'])
            ->middleware('can:transition,grievance')
            ->name('grievances.actions.store');

        // Categorization pipeline.
        Route::post('grievances/{grievance}/accept', [AcceptRejectController::class, 'accept'])
            ->middleware('can:review,grievance')
            ->name('grievances.accept');
        Route::post('grievances/{grievance}/reject', [AcceptRejectController::class, 'reject'])
            ->middleware('can:review,grievance')
            ->name('grievances.reject');
        Route::put('grievances/{grievance}/categorize', [CategorizationController::class, 'update'])
            ->middleware('can:classify,grievance')
            ->name('grievances.categorize');
        Route::put('grievances/{grievance}/org-classify', [OrgClassificationController::class, 'update'])
            ->middleware('can:orgClassify,grievance')
            ->name('grievances.orgClassify');
        Route::patch('grievances/{grievance}/org-classify', [OrgClassificationController::class, 'edit'])
            ->middleware('can:updateOrgClassification,grievance')
            ->name('grievances.orgClassify.edit');

        Route::post('grievances/{grievance}/assign', [AdminAssignmentController::class, 'update'])
            ->middleware('can:assign,grievance')
            ->name('grievances.assign');

        // Attachments (files & documents panel)
        Route::get('grievances/{grievance}/attachments', [AdminAttachmentController::class, 'index'])
            ->middleware('can:view,grievance')
            ->name('grievances.attachments.index');
        Route::post('grievances/{grievance}/attachments', [AdminAttachmentController::class, 'store'])
            ->middleware('can:uploadAttachment,grievance')
            ->name('grievances.attachments.store');
        Route::get('grievances/{grievance}/attachments/{attachment}/download', [AdminAttachmentController::class, 'download'])
            ->middleware('can:view,grievance')
            ->name('grievances.attachments.download');
        Route::delete('grievances/{grievance}/attachments/{attachment}', [AdminAttachmentController::class, 'destroy'])
            ->middleware('can:delete,grievance')
            ->name('grievances.attachments.destroy');

        // Admin closure review workflow
        Route::post('grievances/{grievance}/closure/begin', [AdminClosureController::class, 'beginReview'])
            ->middleware('can:adminReview,grievance')
            ->name('grievances.closure.begin');
        Route::post('grievances/{grievance}/closure/close', [AdminClosureController::class, 'close'])
            ->middleware('can:closureAction,grievance')
            ->name('grievances.closure.close');
        Route::post('grievances/{grievance}/closure/escalate', [AdminClosureController::class, 'escalate'])
            ->middleware('can:closureAction,grievance')
            ->name('grievances.closure.escalate');

        // Per-org grievance type management.
        Route::prefix('org/grievance-types')->name('org.grievance-types.')->group(function (): void {
            Route::get('/', [OrgGrievanceTypeController::class, 'index'])->name('index');
            Route::post('/', [OrgGrievanceTypeController::class, 'store'])->name('store');
            Route::patch('{type}', [OrgGrievanceTypeController::class, 'update'])->name('update');
            Route::delete('{type}', [OrgGrievanceTypeController::class, 'destroy'])->name('destroy');
        });

        // Grievance location override (any role passing the update policy).
        Route::patch('grievances/{grievance}/location', [AdminLocationController::class, 'update'])
            ->middleware('can:update,grievance')
            ->name('grievances.location.update');

        // Geography drill-down CRUD (super-admin only).
        Route::prefix('geography')->name('geography.')->group(function (): void {
            Route::get('/', [AdminGeographyController::class, 'index'])->name('index');
            Route::get('regions/{region}', [AdminGeographyController::class, 'show'])->name('regions.show');
            Route::get('districts/{district}', [AdminGeographyController::class, 'showDistrict'])->name('districts.show');
            Route::get('chiefdoms/{chiefdom}', [AdminGeographyController::class, 'showChiefdom'])->name('chiefdoms.show');
            Route::get('sections/{section}', [AdminGeographyController::class, 'showSection'])->name('sections.show');
            Route::post('{level}', [AdminGeographyController::class, 'store'])->name('store');
            Route::patch('{level}/{id}', [AdminGeographyController::class, 'update'])->name('update');
            Route::delete('{level}/{id}', [AdminGeographyController::class, 'destroy'])->name('destroy');
        });

        // Programme management — grievance-level org/programme selection source.
        Route::get('programmes', [AdminProgrammeController::class, 'index'])
            ->middleware('can:viewAny,'.Programme::class)->name('programmes.index');
        Route::prefix('organizations/{organization}/programmes')->name('organizations.programmes.')->group(function (): void {
            Route::post('/', [AdminProgrammeController::class, 'store'])
                ->middleware('can:create,'.Programme::class)->name('store');
            Route::patch('{programme}', [AdminProgrammeController::class, 'update'])
                ->middleware('can:update,programme')->name('update');
            Route::delete('{programme}', [AdminProgrammeController::class, 'destroy'])
                ->middleware('can:delete,programme')->name('destroy');
        });

        // SLA settings (org-admin + grm-officer).
        Route::prefix('org/sla')->name('org.sla.')->group(function (): void {
            Route::get('/', [SlaSettingsController::class, 'show'])->name('index');
            Route::patch('/', [SlaSettingsController::class, 'updateOrg'])->name('updateOrg');
            Route::patch('programmes/{programme}', [SlaSettingsController::class, 'updateProgramme'])->name('updateProgramme');
        });

        Route::get('reports', [ReportsController::class, 'index'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('reports.index');
        Route::post('reports/preview', [ReportsController::class, 'preview'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('reports.preview');
        Route::post('reports/export', [ReportsController::class, 'export'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('reports.export');
        Route::post('reports/saved', [ReportsController::class, 'saveReport'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('reports.saved.store');
        Route::delete('reports/saved/{report}', [ReportsController::class, 'destroySavedReport'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('reports.saved.destroy');
        Route::get('reports/quarterly', [QuarterlyReportController::class, 'index'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('reports.quarterly');
        Route::get('reports/quarterly/data', [QuarterlyReportController::class, 'fetch'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('reports.quarterly.data');
        Route::post('reports/quarterly/export', [QuarterlyReportController::class, 'export'])
            ->middleware('can:viewAny,'.Grievance::class)
            ->name('reports.quarterly.export');

        // Super-admin user management (wider scope than the org-scoped variant below).
        Route::get('users', [UserController::class, 'index'])
            ->middleware('can:viewAny,'.User::class)->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])
            ->middleware('can:create,'.User::class)->name('users.create');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('can:create,'.User::class)->name('users.store');
        Route::get('users/{user}', [UserController::class, 'show'])
            ->middleware('can:view,user')->name('users.show');
        Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])
            ->middleware('can:assignRoles,user')->name('users.roles.update');

        // Org-scoped user admin with TWO creation paths:
        //   POST /admin/org/users         → direct create (admin sets password)
        //   POST /admin/org/users/invite  → email-invite (password-reset link)
        Route::prefix('org')->name('org.')->group(function (): void {
            Route::get('users', [OrgUserController::class, 'index'])
                ->middleware('can:viewAny,'.User::class)->name('users.index');
            Route::get('users/create', [OrgUserController::class, 'create'])
                ->middleware('can:create,'.User::class)->name('users.create');
            Route::post('users', [OrgUserController::class, 'store'])
                ->middleware('can:create,'.User::class)->name('users.store');
            Route::post('users/invite', [OrgUserController::class, 'invite'])
                ->middleware('can:create,'.User::class)->name('users.invite');

            Route::get('users/{user}', [OrgUserController::class, 'show'])
                ->middleware('can:view,user')->name('users.show');
            Route::get('users/{user}/edit', [OrgUserController::class, 'edit'])
                ->middleware('can:update,user')->name('users.edit');
            Route::put('users/{user}', [OrgUserController::class, 'update'])
                ->middleware('can:update,user')->name('users.update');

            Route::get('users/{user}/role', [OrgUserController::class, 'editRole'])
                ->middleware('can:assignRoles,user')->name('users.editRole');
            Route::put('users/{user}/role', [OrgUserController::class, 'updateRole'])
                ->middleware('can:assignRoles,user')->name('users.updateRole');

            Route::get('users/{user}/password', [OrgUserController::class, 'resetPassword'])
                ->middleware('can:resetPassword,user')->name('users.password');
            Route::post('users/{user}/password/email', [OrgUserController::class, 'sendResetEmail'])
                ->middleware('can:resetPassword,user')->name('users.password.email');
            Route::post('users/{user}/password/set', [OrgUserController::class, 'setPassword'])
                ->middleware('can:resetPassword,user')->name('users.password.set');

            Route::post('users/{user}/deactivate', [OrgUserController::class, 'deactivate'])
                ->middleware('can:deactivate,user')->name('users.deactivate');
            Route::post('users/{user}/reactivate', [OrgUserController::class, 'reactivate'])
                ->middleware('can:deactivate,user')->name('users.reactivate');
        });

        // Role admin (no `show`).
        Route::get('roles', [RoleController::class, 'index'])
            ->middleware('can:viewAny,'.Role::class)->name('roles.index');
        Route::get('roles/create', [RoleController::class, 'create'])
            ->middleware('can:create,'.Role::class)->name('roles.create');
        Route::post('roles', [RoleController::class, 'store'])
            ->middleware('can:create,'.Role::class)->name('roles.store');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('can:update,role')->name('roles.edit');
        Route::put('roles/{role}', [RoleController::class, 'update'])
            ->middleware('can:update,role')->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('can:delete,role')->name('roles.destroy');

        Route::get('audit', [AuditController::class, 'index'])
            ->middleware('can:viewAny,'.AuditEntry::class)->name('audit.index');

        Route::prefix('reference')->name('reference.')->group(function (): void {
            resourceful('grievance-types',    GrievanceTypeController::class,   GrievanceType::class,   'grievance_type',   true);
            Route::post('grievance-types/{grievance_type}/reassign-and-destroy', [GrievanceTypeController::class, 'reassignAndDestroy'])
                ->middleware('can:delete,grievance_type')
                ->name('grievance-types.reassign-and-destroy');
            resourceful('how-reported',       HowReportedController::class,     HowReported::class,     'how_reported',     true);
            resourceful('priorities',         PriorityController::class,        Priority::class,        'priority',         true);
            resourceful('statuses',           StatusController::class,          StatusModel::class,     'status',           true);
            resourceful('action-types',       ActionTypeController::class,      ActionType::class,      'action_type',      true);
            resourceful('areas',              AreaController::class,            Area::class,            'area',             true);
            resourceful('case-concepts',      CaseConceptController::class,     CaseConcept::class,     'case_concept',     true);
            resourceful('review-outcomes',    ReviewOutcomeController::class,   ReviewOutcome::class,   'review_outcome',   true);
            resourceful('satisfactions',      SatisfactionController::class,    Satisfaction::class,    'satisfaction',     true);
            resourceful('feedback-statuses',  FeedbackStatusController::class,  FeedbackStatus::class,  'feedback_status',  true);
        });
    });
});
