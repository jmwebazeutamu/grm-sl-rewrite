<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps `created_by_id` / `updated_by_id` on save. Replaces the legacy
 * `created_by_id`/`modified_by_id` + `date_created`/`last_updated` pattern.
 *
 * Standard Laravel timestamps (`created_at`/`updated_at`) handle the dates.
 */
trait RecordsAuthorship
{
    public static function bootRecordsAuthorship(): void
    {
        static::creating(function ($model): void {
            if (Auth::check() && empty($model->created_by_id)) {
                $model->created_by_id = Auth::id();
                $model->updated_by_id = Auth::id();
            }
        });

        static::updating(function ($model): void {
            if (Auth::check()) {
                $model->updated_by_id = Auth::id();
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Identity\Models\User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Identity\Models\User::class, 'updated_by_id');
    }
}
