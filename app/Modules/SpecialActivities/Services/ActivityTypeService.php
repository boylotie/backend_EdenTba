<?php

namespace App\Modules\SpecialActivities\Services;

use App\Modules\SpecialActivities\Models\ActivityType;
use App\Modules\SpecialActivities\Support\SpecialActivityPublicCache;
use App\Shared\Audit\AuditLogger;

final class ActivityTypeService
{
    /**
     * @param  array{code: string, label: string, is_active: bool}  $data
     */
    public function create(array $data): ActivityType
    {
        $type = ActivityType::create([
            'code' => $data['code'],
            'label' => $data['label'],
            'is_active' => $data['is_active'],
        ]);

        AuditLogger::log(
            'activity_types.create',
            ['code' => $type->code, 'label' => $type->label],
            entityType: 'activity_type',
            entityId: $type->id,
        );

        SpecialActivityPublicCache::invalidate();

        return $type;
    }

    /**
     * @param  array{code: string, label: string, is_active: bool}  $data
     */
    public function update(ActivityType $type, array $data): ActivityType
    {
        $type->fill([
            'code' => $data['code'],
            'label' => $data['label'],
            'is_active' => $data['is_active'],
        ])->save();

        AuditLogger::log(
            'activity_types.update',
            ['code' => $type->code, 'label' => $type->label, 'is_active' => $type->is_active],
            entityType: 'activity_type',
            entityId: $type->id,
        );

        SpecialActivityPublicCache::invalidate();

        return $type;
    }

    /**
     * Supprime un type non utilisé. Retourne false si le type est référencé
     * par des activités (refus — la désactivation reste possible).
     */
    public function delete(ActivityType $type): bool
    {
        if ($type->inUse()) {
            return false;
        }

        $typeId = $type->id;

        $type->delete();

        AuditLogger::log('activity_types.delete', entityType: 'activity_type', entityId: $typeId);

        SpecialActivityPublicCache::invalidate();

        return true;
    }
}
