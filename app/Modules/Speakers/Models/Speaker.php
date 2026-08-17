<?php

namespace App\Modules\Speakers\Models;

use App\Modules\Content\Models\Content;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $title
 * @property string|null $bio
 * @property string|null $photo_path
 * @property bool $is_active
 */
#[Fillable(['name', 'title', 'bio', 'photo_path', 'is_active'])]
class Speaker extends Model
{
    /**
     * Labels français des titres de conférenciers.
     *
     * @var array<string, string>
     */
    public const TITLES = [
        'pasteur' => 'Pasteur',
        'frere' => 'Frère',
        'soeur' => 'Sœur',
        'docteur' => 'Docteur',
        'evangeliste' => 'Évangéliste',
        'autre' => 'Autre',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function titleKeys(): array
    {
        return array_keys(self::TITLES);
    }

    public function label(): string
    {
        return self::TITLES[$this->title].' '.$this->name;
    }

    /**
     * @return HasMany<Content, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    public function inUse(): bool
    {
        return $this->contents()->exists();
    }
}
