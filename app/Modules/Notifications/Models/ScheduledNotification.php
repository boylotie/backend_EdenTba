<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notification programmée par l'administration (US-040) : envoyée par le
 * scheduler quand `scheduled_at` est atteinte, `sent_at` marque l'exécution.
 *
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property Carbon $scheduled_at
 * @property Carbon|null $sent_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'body', 'scheduled_at', 'sent_at', 'created_by'])]
class ScheduledNotification extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
