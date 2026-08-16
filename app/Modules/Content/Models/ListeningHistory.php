<?php

namespace App\Modules\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Position d'écoute d'un utilisateur sur un contenu (MOD-07-P5, US-035).
 * Une seule ligne par couple utilisateur/contenu : la position est mise à jour
 * par upsert à chaque lecture, `completed` indique que la lecture est terminée
 * (reprise depuis le début).
 *
 * @property int $id
 * @property int $user_id
 * @property int $content_id
 * @property int $position_seconds
 * @property bool $completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'content_id', 'position_seconds', 'completed'])]
class ListeningHistory extends Model
{
    protected function casts(): array
    {
        return [
            'position_seconds' => 'int',
            'completed' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Content, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
