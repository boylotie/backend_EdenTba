<?php

namespace App\Modules\Streaming\Models;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $state
 * @property string|null $title
 * @property string|null $description
 * @property string|null $image_path
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $stopped_at
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['state', 'title', 'description', 'image_path', 'started_at', 'stopped_at', 'created_by'])]
class LiveSession extends Model
{
    public const STATE_LIVE = 'live';

    public const STATE_OFF = 'off';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLive(): bool
    {
        return $this->state === self::STATE_LIVE;
    }
}
