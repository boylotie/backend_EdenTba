<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Préférence de notification par type (MOD-09-P4, US-041) : un utilisateur
 * active ou désactive chaque type de notification (`content_published`,
 * `admin_message`, `program_reminder`, `inactivity_reminder`). L'absence de
 * ligne vaut « activé » (défaut).
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'type', 'enabled'])]
class UserNotificationPreference extends Model
{
    protected $table = 'user_notification_preferences';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
