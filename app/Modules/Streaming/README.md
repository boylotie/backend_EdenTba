# Module Streaming (MOD-11)

**Responsabilité** : diffusion audio live réelle (source → encodeur → infrastructure → mobile).

- Modèles : LiveSession (état, métadonnées)
- Rôles : Administrateur (démarrage/arrêt), Utilisateur (écoute)
- Permissions : `streaming.start`, `streaming.stop`
- Référence : `Docs/ROADMAP.md` — MOD-11

Règle absolue : jamais de faux streaming basé sur un fichier téléchargé.
Le protocole, l'encodeur, l'infrastructure, la latence, la sécurité et la reconnexion sont définis en MOD-11-P1 avant toute implémentation.
