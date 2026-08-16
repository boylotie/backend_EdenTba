<?php

namespace App\Support;

use App\Models\User;

final class AdminNavigation
{
    /**
     * Définition centralisée du menu d'administration.
     *
     * Chaque entrée est conditionnée par une permission (`permission`).
     * L'absence de permission sur une entrée la rend visible à tout administrateur.
     *
     * @return array<int, array{heading: string, items: array<int, array{label: string, icon: string, route: string, permission?: string}>}>
     */
    public static function groups(): array
    {
        return [
            [
                'heading' => __('Plateforme'),
                'items' => [
                    ['label' => __('Tableau de bord'), 'icon' => 'home', 'route' => 'dashboard'],
                ],
            ],
            [
                'heading' => __('Administration'),
                'items' => [
                    ['label' => __('Rôles & permissions'), 'icon' => 'shield-check', 'route' => 'admin.roles.index', 'permission' => 'roles.view'],
                    ['label' => __('Journal d\'audit'), 'icon' => 'clipboard-document-list', 'route' => 'admin.audit-logs.index', 'permission' => 'audit.view'],
                    ['label' => __('Paramètres système'), 'icon' => 'cog', 'route' => 'admin.settings.index', 'permission' => 'settings.manage'],
                ],
            ],
            [
                'heading' => __('Organisation'),
                'items' => [
                    ['label' => __('Années & thèmes'), 'icon' => 'calendar-days', 'route' => 'admin.years.index', 'permission' => 'schedule.manage'],
                ],
            ],
            [
                'heading' => __('Activités spéciales'),
                'items' => [
                    ['label' => __('Types d\'activités'), 'icon' => 'tag', 'route' => 'admin.activity-types.index', 'permission' => 'special_activity.manage'],
                    ['label' => __('Activités spéciales'), 'icon' => 'calendar-days', 'route' => 'admin.special-activities.index', 'permission' => 'special_activity.manage'],
                ],
            ],
            [
                'heading' => __('Médias'),
                'items' => [
                    ['label' => __('Contenus audio'), 'icon' => 'folder', 'route' => 'admin.contents.index', 'permission' => 'content.view'],
                    ['label' => __('Direct (live)'), 'icon' => 'radio', 'route' => 'admin.live.index', 'permission' => 'streaming.start'],
                ],
            ],
        ];
    }

    /**
     * Retourne le menu filtré selon les permissions de l'utilisateur.
     *
     * @return array<int, array{heading: string, items: array<int, array{label: string, icon: string, route: string, permission?: string}>}>
     */
    public static function forUser(User $user): array
    {
        $groups = [];

        foreach (self::groups() as $group) {
            $items = [];

            foreach ($group['items'] as $item) {
                if (! isset($item['permission']) || $user->hasPermission($item['permission'])) {
                    $items[] = $item;
                }
            }

            if ($items !== []) {
                $groups[] = [
                    'heading' => $group['heading'],
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }
}
