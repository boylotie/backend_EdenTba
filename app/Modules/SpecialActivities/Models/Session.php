<?php

namespace App\Modules\SpecialActivities\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $special_activity_id
 * @property int $day_of_week
 * @property string $start_time
 * @property int $duration_minutes
 * @property string|null $place
 * @property string|null $theme
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['special_activity_id', 'day_of_week', 'start_time', 'duration_minutes', 'place', 'theme'])]
class Session extends Model
{
    protected $table = 'activity_sessions';

    protected function casts(): array
    {
        return [
            'day_of_week' => 'int',
            'duration_minutes' => 'int',
        ];
    }

    /**
     * @return BelongsTo<SpecialActivity, $this>
     */
    public function specialActivity(): BelongsTo
    {
        return $this->belongsTo(SpecialActivity::class);
    }
}
