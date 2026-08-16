<?php

namespace App\Modules\Playlists\Models;

use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property bool $is_public
 * @property int|null $special_activity_id
 * @property int|null $year_id
 * @property int|null $month_id
 * @property int|null $week_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'is_public', 'special_activity_id', 'year_id', 'month_id', 'week_id'])]
class Playlist extends Model
{
    protected function casts(): array
    {
        return [
            'is_public' => 'bool',
        ];
    }

    /**
     * @return HasMany<PlaylistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('position');
    }

    /**
     * Alias utilisé par le binding scopé des routes `/playlists/{playlist}/items/{playlistItem}`
     * (résolution de `playlistItem` par la relation `playlistItems`).
     *
     * @return HasMany<PlaylistItem, $this>
     */
    public function playlistItems(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<SpecialActivity, $this>
     */
    public function specialActivity(): BelongsTo
    {
        return $this->belongsTo(SpecialActivity::class);
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
}
