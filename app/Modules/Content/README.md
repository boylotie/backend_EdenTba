# Module Content (MOD-05 + MOD-06)

**Responsabilité** : contenus audio (upload, stockage, streaming, métadonnées) et cycle de vie de publication (brouillon → programmé → publié → dépublié → archivé).

- Modèles : Content, ContentStatus (référentiel), métadonnées
- Rôles : Administrateur (gestion), Utilisateur (écoute des contenus publiés)
- Permissions : `content.view`, `content.create`, `content.update`, `content.publish`, `content.delete`
- API : `api/contents/*`, streaming des fichiers (HTTP `Range`)
- Référence : `Docs/ROADMAP.md` — MOD-05, MOD-06

Règles : formats et taille autorisés configurables ; stockage privé ; seuls les contenus `publié` sont visibles publiquement.
