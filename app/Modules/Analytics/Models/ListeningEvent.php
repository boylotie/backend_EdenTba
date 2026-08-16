<?php

namespace App\Modules\Analytics\Models;

use App\Modules\Content\Models\Content;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Événement d'écoute anonymisé (MOD-12-P1, US-048).
 *
 * Aucune donnée personnelle n'est collectée : ni utilisateur, ni IP,
 * ni identifiant d'appareil (règle A2 — anonymisation stricte).
 *
 * @property int $id
 * @property int $content_id
 * @property string $event_type
 * @property int|null $position_seconds
 * @property CarbonInterface $occurred_at
 * @property CarbonInterface|null $created_at
 */
#[Fillable(['content_id', 'event_type', 'position_seconds', 'occurred_at'])]
class ListeningEvent extends Model
{
    public const EVENT_PLAY = 'play';

    public const EVENT_COMPLETED = 'completed';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'position_seconds' => 'int',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public static function eventTypes(): array
    {
        return [self::EVENT_PLAY, self::EVENT_COMPLETED];
    }

    /**
     * @return BelongsTo<Content, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
