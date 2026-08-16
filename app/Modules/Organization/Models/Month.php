<?php

namespace App\Modules\Organization\Models;

use App\Modules\Content\Models\Content;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $year_id
 * @property int $month_number
 * @property string|null $theme
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['year_id', 'month_number', 'theme'])]
class Month extends Model
{
    /**
     * Noms français des mois, indexés par mois (1 à 12).
     *
     * @var array<int, string>
     */
    public const NAMES = [
        1 => 'Janvier',
        2 => 'Février',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Août',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    protected function casts(): array
    {
        return [
            'month_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Year, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    /**
     * @return HasMany<Content, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'month_id');
    }

    /**
     * Un mois contenant des données de niveau inférieur (contenus) ne peut pas
     * être supprimé.
     */
    public function inUse(): bool
    {
        return $this->contents()->exists();
    }
}
