<?php

namespace App\Modules\Speakers\Services;

use App\Modules\Speakers\Models\Speaker;
use App\Shared\Audit\AuditLogger;

final class SpeakerService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Speaker
    {
        $speaker = Speaker::create($data);

        AuditLogger::log(
            'speakers.create',
            ['name' => $speaker->name, 'title' => $speaker->title],
            entityType: 'speaker',
            entityId: $speaker->id,
        );

        return $speaker;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Speaker $speaker, array $data): Speaker
    {
        $speaker->fill($data)->save();

        AuditLogger::log(
            'speakers.update',
            ['name' => $speaker->name, 'title' => $speaker->title],
            entityType: 'speaker',
            entityId: $speaker->id,
        );

        return $speaker;
    }

    public function delete(Speaker $speaker): bool
    {
        if ($speaker->inUse()) {
            return false;
        }

        $speakerId = $speaker->id;

        $speaker->delete();

        AuditLogger::log('speakers.delete', entityType: 'speaker', entityId: $speakerId);

        return true;
    }
}
