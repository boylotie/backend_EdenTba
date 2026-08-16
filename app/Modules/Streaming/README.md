# Module Streaming (MOD-11)

**Responsabilité** : diffusion audio live réelle (source → encodeur → infrastructure → mobile).

- Modèles : LiveSession (état, métadonnées)
- Rôles : Administrateur (démarrage/arrêt), Utilisateur (écoute)
- Permissions : `streaming.start`, `streaming.stop`
- API : `GET /api/v1/live/status` (public + authentifié), `GET /api/v1/live/image` (public), `POST /api/v1/live/start`, `POST /api/v1/live/stop`
- Référence : `Docs/ROADMAP.md` — MOD-11

Règle absolue : jamais de faux streaming basé sur un fichier téléchargé.
Le protocole, l'encodeur, l'infrastructure, la latence, la sécurité et la reconnexion sont définis en MOD-11-P1 (validée, D-14) avant toute implémentation.

## Diffusion micro navigateur (D-15)

Le back-office peut diffuser **sans encodeur externe** : la page « Direct » capture le micro
(`getUserMedia` + `MediaRecorder`, **Opus/WebM** — pas de MP3 en navigateur) et envoie des
chunks au backend (`POST admin/live/stream-chunk`, permission `streaming.start`). Laravel les
stocke dans `storage/app/live` (buffer `LiveChunkBuffer`, marqueur `mic.active`) ; le worker
`php artisan live:relay` les relaie vers la source Icecast (`PUT` source password) tant qu'un
direct est actif **et** que la capture micro tourne.

```bash
# en production, processus long (supervisor/systemd) :
php artisan live:relay            # boucle infinie, intervalle 0.5 s
php artisan live:relay --once     # une seule passe (tests / cron)
```

La logique de relais vit dans `LiveRelayService` (testable via bindings conteneur) ; la
commande `LiveRelayCommand` ne fait que la boucle. Transcodage MP3 (ffmpeg) = évolution ;
compatibilité écoute mobile WebM/Opus à valider en MOD-11-P3. « Arrêter le micro » termine
aussi le direct (état honnête pour les auditeurs).

**P2 livrée (2026-08-16)** : gestion du live backend (état, start/stop, URLs signées HMAC, audit, événements). Voir `Docs/phases/MOD-11-P2-gestion-live.md`.
**P3 livrée (2026-08-16)** : écoute mobile (écran Direct, machine à états, reconnexion backoff 1 s→30 s, renouvellement URL signée). Voir `Docs/phases/MOD-11-P3-ecoute-live.md`.
