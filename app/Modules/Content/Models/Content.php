<?php

namespace App\Modules\Content\Models;

use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Speakers\Models\Speaker;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $status
 * @property string $title
 * @property string|null $description
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $duration_seconds
 * @property string|null $image_path
 * @property string|null $speaker
 * @property int|null $speaker_id
 * @property int|null $year_id
 * @property int|null $month_id
 * @property int|null $week_id
 * @property int|null $special_activity_id
 * @property int|null $day_of_week
 * @property string|null $notes
 * @property string|null $approved_by
 * @property string|null $approval_comment
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $scheduled_at
 * @property int $sort_order
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'status',
    'title',
    'description',
    'file_path',
    'original_filename',
    'mime_type',
    'size_bytes',
    'duration_seconds',
    'image_path',
    'speaker',
    'speaker_id',
    'year_id',
    'month_id',
    'week_id',
    'special_activity_id',
    'day_of_week',
    'notes',
    'approved_by',
    'approval_comment',
    'approved_at',
    'scheduled_at',
    'sort_order',
    'metadata',
])]
class Content extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_UNPUBLISHED = 'unpublished';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Noms français des jours de la semaine, indexés par jour ISO (1 = lundi
     * → 7 = dimanche). Identique à la convention `day_of_week` des programmes
     * et sessions d'activités.
     *
     * @var array<int, string>
     */
    public const DAYS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    /**
     * Matrice des transitions autorisées entre statuts (US-025, A1).
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SCHEDULED, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED],
        self::STATUS_SCHEDULED => [self::STATUS_PUBLISHED, self::STATUS_DRAFT, self::STATUS_UNPUBLISHED, self::STATUS_ARCHIVED],
        self::STATUS_PUBLISHED => [self::STATUS_UNPUBLISHED, self::STATUS_ARCHIVED],
        self::STATUS_UNPUBLISHED => [self::STATUS_PUBLISHED, self::STATUS_SCHEDULED, self::STATUS_DRAFT, self::STATUS_ARCHIVED],
        self::STATUS_ARCHIVED => [],
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'int',
            'duration_seconds' => 'int',
            'scheduled_at' => 'datetime',
            'sort_order' => 'int',
            'day_of_week' => 'int',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SCHEDULED,
            self::STATUS_PUBLISHED,
            self::STATUS_UNPUBLISHED,
            self::STATUS_ARCHIVED,
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isTransitionAllowed(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Destinations autorisées depuis un statut donné.
     *
     * @return list<string>
     */
    public static function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    /**
     * @return BelongsTo<Speaker, $this>
     */
    public function speakerRel(): BelongsTo
    {
        return $this->belongsTo(Speaker::class, 'speaker_id');
    }

    /**
     * @return BelongsTo<Year, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    /**
     * @return BelongsTo<Month, $this>
     */
    public function month(): BelongsTo
    {
        return $this->belongsTo(Month::class);
    }

    /**
     * @return BelongsTo<Week, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }

    /**
     * @return BelongsTo<SpecialActivity, $this>
     */
    public function specialActivity(): BelongsTo
    {
        return $this->belongsTo(SpecialActivity::class);
    }
}
