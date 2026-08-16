<?php

namespace App\Modules\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Token d'appareil d'un utilisateur pour les push notifications (US-039).
 * `provider` vaut `expo` par défaut (D-03) ; la migration vers FCM direct
 * pourra ajouter d'autres fournisseurs.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $provider
 * @property string|null $platform
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'token', 'provider', 'platform', 'last_used_at'])]
class UserDevice extends Model
{
    protected $table = 'user_devices';

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
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
