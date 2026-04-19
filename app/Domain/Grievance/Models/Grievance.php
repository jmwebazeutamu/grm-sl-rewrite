<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Models;

use App\Domain\Grievance\Enums\GrievanceState;
use App\Domain\Identity\Models\User;
use App\Domain\Locality\Models\Chiefdom;
use App\Domain\Locality\Models\District;
use App\Domain\Locality\Models\Locality;
use App\Domain\Locality\Models\Region;
use App\Domain\Locality\Models\Section;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Programme;
use App\Domain\Reference\Models\Area;
use App\Domain\Reference\Models\CaseConcept;
use App\Domain\Reference\Models\GrievanceType;
use App\Domain\Reference\Models\HowReported;
use App\Domain\Reference\Models\Priority;
use App\Support\Concerns\RecordsAuthorship;
use Database\Factories\GrievanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $g_number
 * @property string $summary
 * @property ?string $description
 * @property GrievanceState $state
 * @property bool $is_anonymous
 * @property \Illuminate\Support\Carbon $received_at
 * @property ?\Illuminate\Support\Carbon $resolved_at
 * @property ?\Illuminate\Support\Carbon $closed_at
 */
class Grievance extends Model
{
    use HasFactory;
    use RecordsAuthorship;
    use SoftDeletes;

    protected $table = 'grievance';

    protected $fillable = [
        'g_number', 'summary', 'description',
        'grievance_type_id', 'how_reported_id', 'priority_id',
        'state', 'is_anonymous',
        'category', 'org_classification_id',
        'review_comment', 'reviewed_at', 'reviewed_by_id',
        'accepted_at', 'categorized_at', 'assigned_at',
        'region_id', 'district_id', 'chiefdom_id', 'section_id', 'locality_id',
        'received_at', 'resolved_at', 'closed_at',
        'related_grievance_id',
        'assigned_officer_id',
        'classified_organization_id', 'classified_programme_id',
        'classified_case_concept_id', 'classified_area_id', 'access_id',
        'closure_comment', 'closure_reviewed_by_id', 'closure_reviewed_at',
        'reopened_at',
        'implementing_organization_id', 'programme_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => GrievanceState::class,
            'is_anonymous' => 'boolean',
            'reviewed_at' => 'datetime',
            'received_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'categorized_at' => 'datetime',
            'assigned_at' => 'datetime',
            'closure_reviewed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function closureReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closure_reviewed_by_id');
    }

    public function grievanceType(): BelongsTo
    {
        return $this->belongsTo(GrievanceType::class);
    }

    public function howReported(): BelongsTo
    {
        return $this->belongsTo(HowReported::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function chiefdom(): BelongsTo
    {
        return $this->belongsTo(Chiefdom::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function locationLabel(): string
    {
        $parts = [];
        foreach (['region', 'district', 'chiefdom', 'section', 'locality'] as $rel) {
            $model = $this->getRelationValue($rel);
            if ($model === null) {
                break;
            }
            $parts[] = $model->name;
        }

        return $parts === [] ? '—' : implode(' > ', $parts);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function classifiedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'classified_organization_id');
    }

    public function classifiedProgramme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'classified_programme_id');
    }

    public function implementingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'implementing_organization_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function classifiedCaseConcept(): BelongsTo
    {
        return $this->belongsTo(CaseConcept::class, 'classified_case_concept_id');
    }

    public function classifiedArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'classified_area_id');
    }

    public function orgClassification(): BelongsTo
    {
        return $this->belongsTo(OrgGrievanceType::class, 'org_classification_id');
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(Access::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(GrievanceAction::class)->orderBy('created_at');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(GrievanceFeedback::class);
    }

    public function complainer(): HasOne
    {
        return $this->hasOne(Complainer::class);
    }

    public function suspects(): HasMany
    {
        return $this->hasMany(Suspect::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(Classification::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(GrievanceAttachment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(GrievanceStatusHistory::class)->orderBy('occurred_at');
    }

    public function relatedGrievance(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_grievance_id');
    }

    protected static function newFactory(): GrievanceFactory
    {
        return GrievanceFactory::new();
    }
}
