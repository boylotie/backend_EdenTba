<?php

namespace Database\Seeders;

use App\Modules\Content\Models\Content;
use App\Modules\Speakers\Models\Speaker;
use Illuminate\Database\Seeder;

/**
 * Parse les valeurs uniques du champ texte `speaker` existant sur `contents`
 * et crée des enregistrements Speaker correspondants. Chaque valeur unique
 * (insensible à la casse, trim) devient un Speaker avec le titre "autre".
 * Le `speaker_id` est ensuite renseigné sur chaque contenu correspondant.
 */
class SpeakerMigrationSeeder extends Seeder
{
    public function run(): void
    {
        $distinctSpeakers = Content::query()
            ->whereNotNull('speaker')
            ->where('speaker', '!=', '')
            ->distinct()
            ->pluck('speaker')
            ->mapWithKeys(fn (string $value): array => [mb_strtolower(trim($value)) => trim($value)])
            ->all();

        foreach ($distinctSpeakers as $normalized => $original) {
            $speaker = Speaker::firstOrCreate(
                ['name' => $original],
                ['title' => 'autre'],
            );

            Content::query()
                ->whereRaw('LOWER(TRIM(speaker)) = ?', [$normalized])
                ->update(['speaker_id' => $speaker->id]);
        }
    }
}
