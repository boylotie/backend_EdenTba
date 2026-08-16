<?php

namespace App\Modules\SpecialActivities\Models;

use App\Modules\Content\Models\Content;
use App\Modules\Organization\Models\Week;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $week_id
 * @property int $activity_type_id
 * @property string $name
 * @property string $mode
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['week_id', 'activity_type_id', 'name', 'mode', 'starts_on', 'ends_on'])]
class SpecialActivity extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Week, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(Week::class);
    }

    /**
     * @return BelongsTo<ActivityType, $this>
     */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    /**
     * @return HasMany<Session, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * @return HasMany<Content, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'special_activity_id');
    }

    /**
     * @return HasMany<Playlist, $this>
     */
    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class, 'special_activity_id');
    }

    /**
     * Une activité référencée par des contenus ou playlists ne peut pas être
     * supprimée. Câblé en MOD-05 (contenus) et complété en MOD-08 (playlists).
     */
    public function inUse(): bool
    {
        return $this->contents()->exists() || $this->playlists()->exists();
    }
}
