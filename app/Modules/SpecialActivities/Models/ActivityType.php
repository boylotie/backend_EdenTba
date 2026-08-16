<?php

namespace App\Modules\SpecialActivities\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $label
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'label', 'is_active'])]
class ActivityType extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SpecialActivity, $this>
     */
    public function specialActivities(): HasMany
    {
        return $this->hasMany(SpecialActivity::class);
    }

    /**
     * Un type utilisé par des activités ne peut pas être supprimé.
     * La désactivation (is_active = false) reste possible.
     */
    public function inUse(): bool
    {
        return $this->specialActivities()->exists();
    }
}
