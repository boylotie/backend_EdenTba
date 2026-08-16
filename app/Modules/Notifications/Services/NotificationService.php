<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Content\Models\Content;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\UserNotificationPreference;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Notifications internes (US-038) : création idempotente de notifications pour
 * les destinataires d'un événement métier et gestion du marquage lu/non lu.
 *
 * La création est idempotente : une notification « même type + même ressource »
 * n'est jamais dupliquée pour un même utilisateur (critère d'acceptation A2).
 */
final class NotificationService
{
    public const TYPE_CONTENT_PUBLISHED = 'content_published';

    public const TYPE_ADMIN_MESSAGE = 'admin_message';

    public const TYPE_PROGRAM_REMINDER = 'program_reminder';

    public const TYPE_INACTIVITY_REMINDER = 'inactivity_reminder';

    public const ENTITY_CONTENT = 'content';

    /**
     * Types de notification connus (MOD-09-P4) : une préférence est définie
     * pour chaque type ; toute valeur hors cette liste est rejetée.
     *
     * @return list<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_CONTENT_PUBLISHED,
            self::TYPE_ADMIN_MESSAGE,
            self::TYPE_PROGRAM_REMINDER,
            self::TYPE_INACTIVITY_REMINDER,
        ];
    }

    public static function isKnownType(string $type): bool
    {
        return in_array($type, self::types(), true);
    }

    /**
     * Crée la notification « contenu publié » pour chaque utilisateur actif
     * qui n'a pas désactivé le type `content_published` (MOD-09-P4).
     * Retourne le nombre de notifications réellement créées.
     */
    public function createForContentPublished(Content $content): int
    {
        $created = 0;

        foreach (User::query()
            ->where('is_active', true)
            ->whereDoesntHave('notificationPreferences', $this->disabledPreference(self::TYPE_CONTENT_PUBLISHED))
            ->get() as $user) {
            $notification = $this->createUnique(
                $user->id,
                self::TYPE_CONTENT_PUBLISHED,
                $content->title,
                $content->description,
                self::ENTITY_CONTENT,
                $content->id,
            );

            $created += $notification->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * Crée une notification si une notification équivalente (même utilisateur,
     * type et ressource) n'existe pas déjà. Retourne la notification existante
     * ou créée.
     */
    public function createUnique(
        int $userId,
        string $type,
        string $title,
        ?string $body,
        ?string $entityType,
        ?int $entityId,
    ): Notification {
        return Notification::firstOrCreate(
            ['user_id' => $userId, 'type' => $type, 'entity_type' => $entityType, 'entity_id' => $entityId],
            ['title' => $title, 'body' => $body],
        );
    }

    /**
     * Crée une notification pour chaque utilisateur actif qui n'a pas
     * désactivé le type diffusé (diffusion d'un message d'administration,
     * US-040). Sans ressource associée, donc sans déduplication : chaque
     * diffusion crée un nouvel enregistrement par utilisateur. Retourne le
     * nombre de notifications créées.
     */
    public function createForAllActiveUsers(string $type, string $title, ?string $body = null): int
    {
        $created = 0;

        foreach (User::query()
            ->where('is_active', true)
            ->whereDoesntHave('notificationPreferences', $this->disabledPreference($type))
            ->get() as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Marque une notification comme lue (opération idempotente).
     */
    public function markAsRead(Notification $notification): Notification
    {
        if (! $notification->isRead()) {
            $notification->update(['read_at' => now()]);
            $notification->refresh();
        }

        return $notification;
    }

    /**
     * Notifications d'un utilisateur, plus récentes d'abord.
     *
     * @return LengthAwarePaginator<int, Notification>
     */
    public function paginatedFor(User $user, bool $unreadOnly = false, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Sous-requête « préférence désactivée pour le type » : l'absence de
     * préférence vaut « activé », seule une ligne `enabled=false` exclut.
     *
     * @return Closure(Builder<UserNotificationPreference>): void
     */
    private function disabledPreference(string $type): Closure
    {
        return function (Builder $query) use ($type): void {
            $query->where('type', $type)->where('enabled', false);
        };
    }
}
