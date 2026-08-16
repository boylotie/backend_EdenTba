<?php

namespace App\Modules\Content\Support;

use App\Modules\Content\Models\Content;

final class ContentPresenter
{
    /**
     * Forme normalisée d'un contenu pour l'API (listes et détail public).
     * Factorisée pour que les listes de favoris/historique (MOD-07-P5)
     * exposent exactement la même structure que `/api/v1/contents`.
     *
     * @return array<string, mixed>
     */
    public static function payload(Content $content): array
    {
        return [
            'id' => $content->id,
            'title' => $content->title,
            'status' => $content->status,
            'description' => $content->description,
            'duration_seconds' => $content->duration_seconds,
            'speaker' => $content->speaker,
            'mime_type' => $content->mime_type,
            'size_bytes' => $content->size_bytes,
            'image_url' => $content->image_path !== null ? '/api/v1/contents/'.$content->id.'/image' : null,
            'scheduled_at' => $content->scheduled_at,
            'sort_order' => $content->sort_order,
            'year' => $content->year !== null ? ['id' => $content->year->id, 'label' => $content->year->label] : null,
            'month' => $content->month !== null ? ['id' => $content->month->id, 'month_number' => $content->month->month_number] : null,
            'week' => $content->week !== null ? ['id' => $content->week->id, 'label' => $content->week->label] : null,
            'special_activity' => $content->specialActivity !== null ? ['id' => $content->specialActivity->id, 'name' => $content->specialActivity->name] : null,
        ];
    }
}
