<?php

namespace App\Modules\Organization\Models;

use App\Modules\Content\Models\Content;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $label
 * @property string|null $theme
 * @property bool $is_current
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['label', 'theme', 'is_current'])]
class Year extends Model
{
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Month, $this>
     */
    public function months(): HasMany
    {
        return $this->hasMany(Month::class);
    }

    /**
     * @return HasMany<Week, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(Week::class);
    }

    /**
     * @return HasMany<Content, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'year_id');
    }

    /**
     * Une année utilisée par des données de niveau inférieur ne peut pas être
     * supprimée : semaines, contenus, ou mois contenant des contenus.
     *
     * Les 12 mois structurels (janvier à décembre), même thématisés, ne
     * constituent pas un usage : ils sont supprimés avec l'année.
     */
    public function inUse(): bool
    {
        return $this->weeks()->exists()
            || $this->contents()->exists()
            || $this->months()->whereHas('contents')->exists();
    }
}
