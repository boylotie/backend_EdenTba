# Module Notifications (MOD-09 + MOD-10)

**Responsabilité** : notifications internes, push, programmation, rappels planifiés.

- Modèles : Notification (interne), DeviceToken, NotificationPreference, Reminder
- Rôles : Super Administrateur (supervision), Administrateur (envoi), Utilisateur (réception)
- Permissions : `notification.send`, `notification.schedule`
- Référence : `Docs/ROADMAP.md` — MOD-09, MOD-10

Règles :
- distinguer événement métier, notification interne, push, temps réel (Reverb) et tâche planifiée ;
- Reverb n'est pas un remplacement des push ;
- les rappels et notifications planifiés reposent sur des mécanismes serveur fiables ;
- heures, jours, délais et messages configurables.
