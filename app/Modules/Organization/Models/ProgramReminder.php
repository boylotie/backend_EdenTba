<?php

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Envoi d'un rappel de programme pour une occurrence calendaire (MOD-10-P2).
 *
 * Une ligne par (programme, date d'occurrence) : la présence de `notified_at`
 * garantit l'envoi unique ; un échec laisse `notified_at` null et permet la
 * reprise par la commande `reminders:send-programs`.
 *
 * @property int $id
 * @property int $program_id
 * @property string $occurrence_date
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['program_id', 'occurrence_date', 'notified_at'])]
class ProgramReminder extends Model
{
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
