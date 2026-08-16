<?php

namespace Database\Seeders;

use App\Modules\SpecialActivities\Models\ActivityType;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    /**
     * Types d'activités documentés (PROJECT_CONTEXT.md). Données initiales
     * configurables : jamais codées en dur dans le code métier.
     *
     * @return array<int, array{code: string, label: string}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => 'prayer', 'label' => 'Prière'],
            ['code' => 'seminar', 'label' => 'Séminaire'],
            ['code' => 'convention', 'label' => 'Convention'],
            ['code' => 'campaign', 'label' => 'Campagne'],
            ['code' => 'retreat', 'label' => 'Retraite'],
            ['code' => 'other', 'label' => 'Autre'],
        ];
    }

    public function run(): void
    {
        foreach (self::defaults() as $type) {
            ActivityType::updateOrCreate(['code' => $type['code']], [
                'label' => $type['label'],
                'is_active' => true,
            ]);
        }
    }
}
