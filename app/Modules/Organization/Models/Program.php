<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $week_id
 * @property int $day_of_week
 * @property string $start_time
 * @property int $duration_minutes
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['week_id', 'day_of_week', 'start_time', 'duration_minutes', 'type'])]
class Program extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => 'int',
            'duration_minutes' => 'int',
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
     * Un programme référencé par des contenus ne peut pas être supprimé.
     *
     * Sera câblé lors de MOD-05 (contenus).
     */
    public function inUse(): bool
    {
        return false;
    }
}
