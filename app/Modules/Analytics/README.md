# Module Analytics (MOD-12)

**Responsabilité** : statistiques d'écoute, optimisation des performances.

## MOD-12-P1 — Statistiques d'écoute (Livré, 2026-08-16)

- Modèles : `ListeningEvent` (anonymisé), `StatisticsReport` (ressource de consultation)
- Endpoints :
  - `POST /api/v1/listening-events` (public, événements anonymes `play`/`completed`)
  - `GET /api/v1/admin/statistics` (permission `statistics.view`)
- Services : `StatisticsService` (agrégats SQL : totaux, par contenu, par période)
- Permissions : `statistics.view` (Super Administrateur, Administrateur)
- Règles : événements anonymisés ; respect de la vie privée ; permissions adaptées ; état vide explicite
- Référence : `Docs/phases/MOD-12-P1-statistiques.md`, `Docs/ROADMAP.md` — MOD-12

## MOD-12-P2 — Optimisations et performances (Livré, 2026-08-16)

- Cache cohérent : invalidation à l'écriture des caches publics (contenus via `ContentService` ; navigation via `OrganizationPublicCache` ; activités via `SpecialActivityPublicCache`), clés versionnées dans les contrôleurs publics.
- Index d'optimisation : `programs (week_id, day_of_week, start_time)`, `activity_sessions (special_activity_id, day_of_week, start_time)`, `contents (status, sort_order)`.
- Mobile : listes paginées en `FlatList`, mémoïsation des lignes, contexte lecteur scindé (`usePlayerStatus`).
- Tests : `tests/Feature/Cache/PublicCacheInvalidationTest.php` ; `composer test` 473 Pest / 1584 assertions verts.
- Référence : `Docs/phases/MOD-12-P2-optimisations.md`, `Docs/ROADMAP.md` — MOD-12
