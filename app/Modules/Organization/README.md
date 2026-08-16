# Module Organization (MOD-03)

**Responsabilité** : hiérarchie Année → Thème annuel → Mois → Thème mensuel → Semaine → Programme / activité.

- Modèles : Year, YearTheme, Month, MonthTheme, Week, Program
- Rôles : Administrateur / Communication (gestion), Utilisateur (lecture)
- Permissions : `schedule.manage`
- API : `api/organization/*` (lecture publique des données publiées)
- Référence : `Docs/ROADMAP.md` — MOD-03

Règles : les jours et heures de programme sont configurables, jamais codés en dur.
Cache public : navigation cache-ée TTL 300 s, clé versionnée (`OrganizationPublicCache::VERSION_KEY`) invalidée à chaque écriture (années, mois, semaines, programmes) — MOD-12-P2.
