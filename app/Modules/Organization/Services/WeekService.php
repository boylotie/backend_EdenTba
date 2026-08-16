<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Week;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Support\OrganizationPublicCache;
use App\Shared\Audit\AuditLogger;

final class WeekService
{
    /**
     * @param  array{label: string}  $data
     */
    public function create(Year $year, array $data): Week
    {
        $week = Week::create([
            'year_id' => $year->id,
            'label' => $data['label'],
        ]);

        AuditLogger::log(
            'organization.weeks.create',
            ['year_id' => $year->id, 'label' => $week->label],
            entityType: 'week',
            entityId: $week->id,
        );

        OrganizationPublicCache::invalidate();

        return $week;
    }

    /**
     * @param  array{label: string}  $data
     */
    public function update(Week $week, array $data): Week
    {
        $week->fill([
            'label' => $data['label'],
        ])->save();

        AuditLogger::log(
            'organization.weeks.update',
            ['year_id' => $week->year_id, 'label' => $week->label],
            entityType: 'week',
            entityId: $week->id,
        );

        OrganizationPublicCache::invalidate();

        return $week;
    }

    /**
     * Supprime une semaine non utilisée. Retourne false si la semaine est en
     * cours d'utilisation (refus).
     */
    public function delete(Week $week): bool
    {
        if ($week->inUse()) {
            return false;
        }

        $weekId = $week->id;

        $week->delete();

        AuditLogger::log('organization.weeks.delete', entityType: 'week', entityId: $weekId);

        OrganizationPublicCache::invalidate();

        return true;
    }
}
