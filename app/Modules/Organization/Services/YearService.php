<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Year;
use App\Modules\Organization\Support\OrganizationPublicCache;
use App\Shared\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

final class YearService
{
    /**
     * @param  array{label: string, theme?: string|null, is_current?: bool}  $data
     */
    public function create(array $data): Year
    {
        $year = DB::transaction(function () use ($data): Year {
            $year = Year::create([
                'label' => $data['label'],
                'theme' => $data['theme'] ?? null,
                'is_current' => (bool) ($data['is_current'] ?? false),
            ]);

            $this->createDefaultMonths($year);

            if ($year->is_current) {
                $this->resetCurrentExcept($year->id);
            }

            return $year;
        });

        AuditLogger::log('organization.years.create', ['label' => $year->label], entityType: 'year', entityId: $year->id);

        OrganizationPublicCache::invalidate();

        return $year;
    }

    /**
     * @param  array{label: string, theme?: string|null, is_current?: bool}  $data
     */
    public function update(Year $year, array $data): Year
    {
        $fill = [
            'label' => $data['label'],
            'theme' => $data['theme'] ?? null,
        ];

        if (array_key_exists('is_current', $data)) {
            $fill['is_current'] = (bool) $data['is_current'];
        }

        $year->fill($fill)->save();

        if ($year->is_current) {
            $this->resetCurrentExcept($year->id);
        }

        AuditLogger::log('organization.years.update', ['label' => $year->label], entityType: 'year', entityId: $year->id);

        OrganizationPublicCache::invalidate();

        return $year;
    }

    /**
     * Désigne l'année courante (les autres cessent de l'être).
     */
    public function markCurrent(Year $year): Year
    {
        $this->resetCurrentExcept($year->id);
        $year->update(['is_current' => true]);

        AuditLogger::log('organization.years.set_current', entityType: 'year', entityId: $year->id);

        OrganizationPublicCache::invalidate();

        return $year;
    }

    /**
     * Supprime une année non utilisée. Retourne false si l'année est en cours
     * d'utilisation (refus).
     */
    public function delete(Year $year): bool
    {
        if ($year->inUse()) {
            return false;
        }

        $yearId = $year->id;

        DB::transaction(function () use ($year): void {
            // Les 12 mois structurels (vides) sont supprimés avec l'année.
            $year->months()->delete();
            $year->delete();
        });

        AuditLogger::log('organization.years.delete', entityType: 'year', entityId: $yearId);

        OrganizationPublicCache::invalidate();

        return true;
    }

    /**
     * Crée automatiquement les 12 mois de l'année, de janvier à décembre.
     */
    private function createDefaultMonths(Year $year): void
    {
        $year->months()->createMany(
            array_map(
                static fn (int $monthNumber): array => ['month_number' => $monthNumber],
                range(1, 12),
            ),
        );
    }

    private function resetCurrentExcept(int $exceptId): void
    {
        Year::query()
            ->where('is_current', true)
            ->whereKeyNot($exceptId)
            ->update(['is_current' => false]);
    }
}
