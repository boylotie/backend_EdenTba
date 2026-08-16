<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notification interne destinée à un utilisateur (US-038).
 *
 * `entity_type` / `entity_id` désignent la ressource associée (contenu,
 * playlist, etc.) de façon polymorphe, comme pour les journaux d'audit.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'type', 'title', 'body', 'entity_type', 'entity_id', 'read_at'])]
class Notification extends Model
{
    protected $table = 'user_notifications';

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
