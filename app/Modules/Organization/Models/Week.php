<?php

namespace App\Modules\Organization\Models;

use App\Modules\Content\Models\Content;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year_id
 * @property string $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['year_id', 'label'])]
class Week extends Model
{
    /**
     * @return BelongsTo<Year, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * @return HasMany<SpecialActivity, $this>
     */
    public function specialActivities(): HasMany
    {
        return $this->hasMany(SpecialActivity::class);
    }

    /**
     * @return HasMany<Content, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'week_id');
    }

    /**
     * Une semaine contenant des données de niveau inférieur (programmes,
     * activités spéciales, sessions, contenus) ne peut pas être supprimée.
     */
    public function inUse(): bool
    {
        return $this->programs()->exists()
            || $this->specialActivities()->exists()
            || $this->contents()->exists();
    }
}
