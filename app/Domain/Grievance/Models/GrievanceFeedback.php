<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Grievance\Enums\FeedbackRating;
use Database\Factories\GrievanceFeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrievanceFeedback extends Model
{
    use HasFactory;

    protected $table = 'grievance_feedback';

    protected $fillable = ['grievance_id', 'rating', 'comment', 'channel', 'submitted_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => FeedbackRating::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function grievance(): BelongsTo
    {
        return $this->belongsTo(Grievance::class);
    }

    protected static function newFactory(): GrievanceFeedbackFactory
    {
        return GrievanceFeedbackFactory::new();
    }
}
