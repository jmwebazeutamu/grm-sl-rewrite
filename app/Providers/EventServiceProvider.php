<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Audit\Listeners\LogGrievanceStateChanged;
use App\Domain\Audit\Listeners\LogGrievanceSubmitted;
use App\Domain\Audit\Listeners\LogUserRoleAssigned;
use App\Domain\Grievance\Events\FeedbackRequested;
use App\Domain\Grievance\Events\GrievanceOfficerAssigned;
use App\Domain\Grievance\Events\GrievanceReopened;
use App\Domain\Grievance\Events\GrievanceStateChanged;
use App\Domain\Grievance\Events\GrievanceSubmitted;
use App\Domain\Grievance\Listeners\NotifyOrgOnReopen;
use App\Domain\Grievance\Listeners\NotifyStaffOnAssignment;
use App\Domain\Grievance\Listeners\NotifyStaffOnStateChange;
use App\Domain\Grievance\Listeners\SendFeedbackInvitation;
use App\Domain\Grievance\Listeners\SendGrievanceReceivedNotification;
use App\Domain\Grievance\Listeners\SendStateChangeNotification;
use App\Domain\Identity\Events\UserDeactivated;
use App\Domain\Identity\Events\UserReactivated;
use App\Domain\Identity\Events\UserRoleAssigned;
use App\Domain\Identity\Listeners\LogUserStatusChange;
use App\Domain\Reporting\Listeners\InvalidateQuarterlyCache;
use App\Domain\Reporting\Listeners\InvalidateReportCache;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, array<int, class-string>> */
    protected $listen = [
        GrievanceSubmitted::class => [
            InvalidateReportCache::class,
            SendGrievanceReceivedNotification::class,
            LogGrievanceSubmitted::class,
        ],
        GrievanceStateChanged::class => [
            InvalidateReportCache::class,
            InvalidateQuarterlyCache::class,
            SendStateChangeNotification::class,
            NotifyStaffOnStateChange::class,
            LogGrievanceStateChanged::class,
        ],
        FeedbackRequested::class => [
            SendFeedbackInvitation::class,
        ],
        GrievanceReopened::class => [
            NotifyOrgOnReopen::class,
        ],
        GrievanceOfficerAssigned::class => [
            NotifyStaffOnAssignment::class,
        ],
        UserRoleAssigned::class => [
            LogUserRoleAssigned::class,
        ],
        UserDeactivated::class => [
            LogUserStatusChange::class,
        ],
        UserReactivated::class => [
            LogUserStatusChange::class,
        ],
    ];
}
