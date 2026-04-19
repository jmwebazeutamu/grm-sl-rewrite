<?php

declare(strict_types=1);

use App\Domain\Grievance\Http\Controllers\Mobile\MobileAuthController;
use App\Domain\Grievance\Http\Controllers\Mobile\MobileDashboardController;
use App\Domain\Grievance\Http\Controllers\Mobile\MobileGrievanceController;
use App\Domain\Grievance\Http\Controllers\Mobile\MobilePushController;
use App\Domain\Grievance\Http\Controllers\Mobile\MobileReferenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (v1)
|--------------------------------------------------------------------------
| Consumed by the Expo React Native client. No Inertia, no CSRF, all JSON.
| Prefix '/api/v1/mobile' is set in bootstrap/app.php.
*/

// PUBLIC — no auth
Route::post('auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:10,1');

Route::prefix('reference')->middleware('throttle:120,1')->group(function (): void {
    Route::get('regions',       [MobileReferenceController::class, 'regions']);
    Route::get('districts',     [MobileReferenceController::class, 'districts']);
    Route::get('chiefdoms',     [MobileReferenceController::class, 'chiefdoms']);
    Route::get('sections',      [MobileReferenceController::class, 'sections']);
    Route::get('localities',    [MobileReferenceController::class, 'localities']);
    Route::get('types',         [MobileReferenceController::class, 'types']);
    Route::get('how-reported',  [MobileReferenceController::class, 'howReported']);
    Route::get('organisations', [MobileReferenceController::class, 'organisations']);
    Route::get('programmes',    [MobileReferenceController::class, 'programmes']);
});

Route::post('grievances',         [MobileGrievanceController::class, 'submit'])->middleware('throttle:20,60');
Route::get('grievances/{ref}/track', [MobileGrievanceController::class, 'track'])
    ->where('ref', '[A-Z0-9/\-]+')
    ->middleware('throttle:60,1');

// AUTH — Sanctum token
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [MobileAuthController::class, 'logout']);
    Route::get('auth/me',      [MobileAuthController::class, 'me']);

    Route::get('dashboard', [MobileDashboardController::class, 'index']);

    Route::get('grievances',          [MobileGrievanceController::class, 'index']);
    Route::get('grievances/{id}',     [MobileGrievanceController::class, 'show'])->whereNumber('id');
    Route::get('grievances/{id}/timeline', [MobileGrievanceController::class, 'timeline'])->whereNumber('id');
    Route::post('grievances/{id}/actions',      [MobileGrievanceController::class, 'postAction'])->whereNumber('id');
    Route::post('grievances/{id}/attachments',  [MobileGrievanceController::class, 'uploadAttachment'])->whereNumber('id');
    Route::post('grievances/{id}/review',       [MobileGrievanceController::class, 'review'])->whereNumber('id');
    Route::post('grievances/{id}/assign',       [MobileGrievanceController::class, 'assign'])->whereNumber('id');
    Route::post('grievances/{id}/closure/begin',    [MobileGrievanceController::class, 'beginClosure'])->whereNumber('id');
    Route::post('grievances/{id}/closure/close',    [MobileGrievanceController::class, 'close'])->whereNumber('id');
    Route::post('grievances/{id}/closure/escalate', [MobileGrievanceController::class, 'escalate'])->whereNumber('id');

    Route::post('push/register',   [MobilePushController::class, 'register']);
    Route::post('push/unregister', [MobilePushController::class, 'unregister']);
});
