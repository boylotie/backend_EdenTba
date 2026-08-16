<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Month;
use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Support\OrganizationPublicCache;
use App\Shared\Audit\AuditLogger;

final class MonthService
{
    /**
     * @param  array{month_number: int, theme?: string|null}  $data
     */
    public function create(Year $year, array $data): Month
    {
        $month = Month::create([
            'year_id' => $year->id,
            'month_number' => $data['month_number'],
            'theme' => $data['theme'] ?? null,
        ]);

        AuditLogger::log(
            'organization.months.create',
            ['year_id' => $year->id, 'month_number' => $month->month_number],
            entityType: 'month',
            entityId: $month->id,
        );

        OrganizationPublicCache::invalidate();

        return $month;
    }

    /**
     * @param  array{month_number: int, theme?: string|null}  $data
     */
    public function update(Month $month, array $data): Month
    {
        $month->fill([
            'month_number' => $data['month_number'],
            'theme' => $data['theme'] ?? null,
        ])->save();

        AuditLogger::log(
            'organization.months.update',
            ['year_id' => $month->year_id, 'month_number' => $month->month_number],
            entityType: 'month',
            entityId: $month->id,
        );

        OrganizationPublicCache::invalidate();

        return $month;
    }

    /**
     * Supprime un mois non utilisé. Retourne false si le mois est en cours
     * d'utilisation (refus).
     */
    public function delete(Month $month): bool
    {
        if ($month->inUse()) {
            return false;
        }

        $monthId = $month->id;

        $month->delete();

        AuditLogger::log('organization.months.delete', entityType: 'month', entityId: $monthId);

        OrganizationPublicCache::invalidate();

        return true;
    }
}
