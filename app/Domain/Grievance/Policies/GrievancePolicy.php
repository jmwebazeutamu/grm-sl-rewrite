<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Policies;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\Organization;

/**
 * Super-admin bypass is in AuthServiceProvider via Gate::before.
 *
 * The categorization pipeline introduces:
 *   - classify: initial category + org on accepted case (ACC/supervisor)
 *   - recategorize: always false (super-admin via Gate::before)
 *   - orgClassify: org sub-type (owning-org staff, state=assigned)
 */
class GrievancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('grievance.viewAny');
    }

    public function view(User $user, Grievance $grievance): bool
    {
        if (! $user->can('grievance.view')) {
            return false;
        }

        return $this->userBelongsToOwningOrg($user, $grievance);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Grievance $grievance): bool
    {
        if ($grievance->state->isTerminal()) {
            return false;
        }

        if (! $user->can('grievance.update')) {
            return false;
        }

        if (! $this->userBelongsToOwningOrg($user, $grievance)) {
            return false;
        }

        if ($this->isSupervisor($user)) {
            return true;
        }

        if ($this->inClosureWindow($grievance) && $this->isOrgRole($user)) {
            return false;
        }

        return $this->canAdminister($user) || $this->isAssignedTo($user, $grievance);
    }

    public function delete(User $user, Grievance $grievance): bool
    {
        return false;
    }

    public function transition(User $user, Grievance $grievance): bool
    {
        if ($grievance->state->isTerminal()) {
            return false;
        }

        if (! $user->can('grievance.transition')) {
            return false;
        }

        if (! $this->userBelongsToOwningOrg($user, $grievance)) {
            return false;
        }

        if ($this->isSupervisor($user)) {
            return true;
        }

        if ($this->inClosureWindow($grievance) && $this->isOrgRole($user)) {
            return false;
        }

        return $this->canAdminister($user) || $this->isAssignedTo($user, $grievance);
    }

    public function assign(User $user, Grievance $grievance): bool
    {
        if ($grievance->state->isTerminal()) {
            return false;
        }

        if ($this->inClosureWindow($grievance)) {
            return false;
        }

        if (! $user->can('grievance.assign')) {
            return false;
        }

        return $this->userBelongsToOwningOrg($user, $grievance);
    }

    /** Initial categorization — ACC sets category + org on accepted case. */
    public function classify(User $user, Grievance $grievance): bool
    {
        if ($grievance->state !== GrievanceState::Accepted) {
            return false;
        }

        if (! $user->can('grievance.assign')) {
            return false;
        }

        return $this->isAccMember($user) || $this->isSupervisor($user);
    }

    /** Change category/org AFTER categorization. Always false; super-admin via Gate::before. */
    public function recategorize(User $user, Grievance $grievance): bool
    {
        return false;
    }

    /** Org sets their internal sub-classification. Owning-org only, state=assigned. */
    public function orgClassify(User $user, Grievance $grievance): bool
    {
        if ($grievance->state !== GrievanceState::Assigned) {
            return false;
        }

        if (! $user->can('grievance.transition')) {
            return false;
        }

        return $this->userBelongsToOwningOrg($user, $grievance);
    }

    /**
     * Edit an already-set sub-classification without retriggering the state
     * transition. Available to org-admin of the owning org (super-admin
     * passes via Gate::before) in any active, non-closure state once the
     * case has already been categorised.
     */
    public function updateOrgClassification(User $user, Grievance $grievance): bool
    {
        if ($grievance->state->isTerminal()) {
            return false;
        }

        if ($this->inClosureWindow($grievance)) {
            return false;
        }

        if (! $user->can('grievance.update')) {
            return false;
        }

        if (! $user->hasRole('org-admin')) {
            return false;
        }

        return $this->userBelongsToOwningOrg($user, $grievance);
    }

    public function uploadAttachment(User $user, Grievance $grievance): bool
    {
        if (! $user->can('grievance.update')) {
            return false;
        }

        if (! $this->userBelongsToOwningOrg($user, $grievance)) {
            return false;
        }

        if ($this->isSupervisor($user)) {
            return true;
        }

        if ($this->inClosureWindow($grievance) && $this->isOrgRole($user)) {
            return false;
        }

        if ($this->canAdminister($user)) {
            return true;
        }

        if ($user->hasRole('organization-officer')) {
            return $this->isAssignedTo($user, $grievance);
        }

        return true;
    }

    /** Admin closure review — begin review from resolved. */
    public function adminReview(User $user, Grievance $grievance): bool
    {
        if (! in_array($grievance->state, [GrievanceState::Resolved, GrievanceState::UnderAdminReview], true)) {
            return false;
        }

        return $user->hasAnyRole(['acc-reviewer', 'grm-data-operator', 'super-admin']);
    }

    /** Admin closure action — close or escalate from under_admin_review. */
    public function closureAction(User $user, Grievance $grievance): bool
    {
        if ($grievance->state !== GrievanceState::UnderAdminReview) {
            return false;
        }

        return $user->hasAnyRole(['acc-reviewer', 'grm-data-operator', 'super-admin']);
    }

    public function review(User $user, Grievance $grievance): bool
    {
        if (! in_array($grievance->state, [GrievanceState::Submitted, GrievanceState::UnderReview], true)) {
            return false;
        }

        if (! $user->can('grievance.review')) {
            return false;
        }

        return $this->isAccMember($user) || $this->isSupervisor($user);
    }

    private function userBelongsToOwningOrg(User $user, Grievance $grievance): bool
    {
        if ($this->isSupervisor($user)) {
            return true;
        }

        // Pipeline states before org assignment — ACC only.
        if (in_array($grievance->state, [
            GrievanceState::Submitted,
            GrievanceState::UnderReview,
            GrievanceState::Accepted,
            GrievanceState::Categorized,
        ], true)) {
            return $this->isAccMember($user);
        }

        if ($grievance->classified_organization_id === null) {
            return false;
        }

        return $user->organization_id === $grievance->classified_organization_id;
    }

    private function canAdminister(User $user): bool
    {
        return $user->can('grievance.assign');
    }

    private function isAssignedTo(User $user, Grievance $grievance): bool
    {
        return $grievance->assigned_officer_id !== null
            && $grievance->assigned_officer_id === $user->id;
    }

    private function isSupervisor(User $user): bool
    {
        return $user->organization_id === null
            || $user->hasRole('grm-data-operator');
    }

    private function inClosureWindow(Grievance $grievance): bool
    {
        return in_array($grievance->state, [
            GrievanceState::Resolved,
            GrievanceState::UnderAdminReview,
        ], true);
    }

    private function isOrgRole(User $user): bool
    {
        return $user->hasAnyRole(['org-admin', 'grm-officer', 'organization-officer']);
    }

    private function isAccMember(User $user): bool
    {
        static $accId = null;

        if ($accId === null) {
            $accId = Organization::where('acronym', config('grm.acc_acronym', 'ACC'))
                ->value('id') ?? 0;
        }

        return $user->organization_id === $accId;
    }
}
