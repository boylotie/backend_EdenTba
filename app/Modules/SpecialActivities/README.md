# Module SpecialActivities (MOD-04)

**Responsabilité** : activités spéciales configurables (semaine de prière, séminaire, convention, campagne, retraite, autre), sessions et rattachement aux semaines.

- Modèles : ActivityType, SpecialActivity, Session
- Rôles : Administrateur (création), Utilisateur (lecture)
- Permissions : `special_activity.manage`
- API : lecture publique des activités publiées
- Référence : `Docs/ROADMAP.md` — MOD-04

Règle : les types d'activités sont configurables, jamais codés en dur.
Cache public : activités et sessions cache-ées TTL 300 s, clé versionnée (`SpecialActivityPublicCache::VERSION_KEY`) invalidée à chaque écriture (activités, sessions, types) — MOD-12-P2.
