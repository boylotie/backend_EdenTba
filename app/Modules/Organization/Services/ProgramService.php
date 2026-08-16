<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Program;
use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Support\OrganizationPublicCache;
use App\Shared\Audit\AuditLogger;

final class ProgramService
{
    /**
     * @param  array{day_of_week: int, start_time: string, duration_minutes: int, type: string}  $data
     */
    public function create(Week $week, array $data): Program
    {
        $program = Program::create([
            'week_id' => $week->id,
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'duration_minutes' => $data['duration_minutes'],
            'type' => $data['type'],
        ]);

        AuditLogger::log(
            'organization.programs.create',
            [
                'week_id' => $week->id,
                'day_of_week' => $program->day_of_week,
                'start_time' => $program->start_time,
                'duration_minutes' => $program->duration_minutes,
                'type' => $program->type,
            ],
            entityType: 'program',
            entityId: $program->id,
        );

        OrganizationPublicCache::invalidate();

        return $program;
    }

    /**
     * @param  array{day_of_week: int, start_time: string, duration_minutes: int, type: string}  $data
     */
    public function update(Program $program, array $data): Program
    {
        $program->fill([
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'duration_minutes' => $data['duration_minutes'],
            'type' => $data['type'],
        ])->save();

        AuditLogger::log(
            'organization.programs.update',
            [
                'week_id' => $program->week_id,
                'day_of_week' => $program->day_of_week,
                'start_time' => $program->start_time,
                'duration_minutes' => $program->duration_minutes,
                'type' => $program->type,
            ],
            entityType: 'program',
            entityId: $program->id,
        );

        OrganizationPublicCache::invalidate();

        return $program;
    }

    /**
     * Supprime un programme non référencé. Retourne false si le programme est
     * en cours d'utilisation (refus).
     */
    public function delete(Program $program): bool
    {
        if ($program->inUse()) {
            return false;
        }

        $programId = $program->id;

        $program->delete();

        AuditLogger::log('organization.programs.delete', entityType: 'program', entityId: $programId);

        OrganizationPublicCache::invalidate();

        return true;
    }
}
