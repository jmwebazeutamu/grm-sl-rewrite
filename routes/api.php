<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

// Public reference data — cached, rate-limited.
Route::middleware('throttle:60,1')->prefix('v1/reference')->group(function (): void {
    // Route::get('/grievance-types', [...]);
    // Route::get('/localities', [...]);
});
