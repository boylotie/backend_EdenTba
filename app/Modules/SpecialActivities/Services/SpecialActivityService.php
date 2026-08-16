<?php

namespace App\Modules\SpecialActivities\Services;

use App\Modules\SpecialActivities\Models\Session;
use App\Modules\SpecialActivities\Models\SpecialActivity;
use App\Modules\SpecialActivities\Support\SessionSchedule;
use App\Modules\SpecialActivities\Support\SpecialActivityPublicCache;
use App\Shared\Audit\AuditLogger;
use DomainException;

final class SpecialActivityService
{
    /**
     * @param  array{week_id: int, activity_type_id: int, name: string, mode: string, starts_on: string|null, ends_on: string|null}  $data
     */
    public function create(array $data): SpecialActivity
    {
        $activity = SpecialActivity::create([
            'week_id' => $data['week_id'],
            'activity_type_id' => $data['activity_type_id'],
            'name' => $data['name'],
            'mode' => $data['mode'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
        ]);

        AuditLogger::log(
            'special_activities.create',
            ['week_id' => $activity->week_id, 'name' => $activity->name, 'mode' => $activity->mode],
            entityType: 'special_activity',
            entityId: $activity->id,
        );

        SpecialActivityPublicCache::invalidate();

        return $activity;
    }

    /**
     * @param  array{week_id: int, activity_type_id: int, name: string, mode: string, starts_on: string|null, ends_on: string|null}  $data
     */
    public function update(SpecialActivity $activity, array $data): SpecialActivity
    {
        $activity->fill([
            'week_id' => $data['week_id'],
            'activity_type_id' => $data['activity_type_id'],
            'name' => $data['name'],
            'mode' => $data['mode'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
        ])->save();

        AuditLogger::log(
            'special_activities.update',
            ['week_id' => $activity->week_id, 'name' => $activity->name, 'mode' => $activity->mode],
            entityType: 'special_activity',
            entityId: $activity->id,
        );

        SpecialActivityPublicCache::invalidate();

        return $activity;
    }

    /**
     * Supprime une activité non référencée. Retourne false si l'activité est
     * en cours d'utilisation (refus). Les sessions sont supprimées en cascade.
     */
    public function delete(SpecialActivity $activity): bool
    {
        if ($activity->inUse()) {
            return false;
        }

        $activityId = $activity->id;

        $activity->delete();

        AuditLogger::log('special_activities.delete', entityType: 'special_activity', entityId: $activityId);

        SpecialActivityPublicCache::invalidate();

        return true;
    }

    /**
     * @param  array{day_of_week: int, start_time: string, duration_minutes: int, place: string|null, theme: string|null}  $data
     *
     * @throws DomainException si la session chevauche une autre session de la même activité
     */
    public function addSession(SpecialActivity $activity, array $data): Session
    {
        if (SessionSchedule::hasOverlap(
            $activity,
            null,
            $data['day_of_week'],
            $data['start_time'],
            $data['duration_minutes'],
        )) {
            throw new DomainException('Cette session chevauche une autre session de la même activité.');
        }

        $session = Session::create([
            'special_activity_id' => $activity->id,
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'duration_minutes' => $data['duration_minutes'],
            'place' => $data['place'],
            'theme' => $data['theme'],
        ]);

        AuditLogger::log(
            'special_activities.sessions.create',
            [
                'special_activity_id' => $activity->id,
                'day_of_week' => $session->day_of_week,
                'start_time' => $session->start_time,
            ],
            entityType: 'session',
            entityId: $session->id,
        );

        SpecialActivityPublicCache::invalidate();

        return $session;
    }

    /**
     * @param  array{day_of_week: int, start_time: string, duration_minutes: int, place: string|null, theme: string|null}  $data
     *
     * @throws DomainException si la session modifiée chevauche une autre session de la même activité
     */
    public function updateSession(Session $session, array $data): Session
    {
        if (SessionSchedule::hasOverlap(
            $session->specialActivity,
            $session->id,
            $data['day_of_week'],
            $data['start_time'],
            $data['duration_minutes'],
        )) {
            throw new DomainException('Cette session chevauche une autre session de la même activité.');
        }

        $session->fill([
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'duration_minutes' => $data['duration_minutes'],
            'place' => $data['place'],
            'theme' => $data['theme'],
        ])->save();

        AuditLogger::log(
            'special_activities.sessions.update',
            [
                'special_activity_id' => $session->special_activity_id,
                'day_of_week' => $session->day_of_week,
                'start_time' => $session->start_time,
            ],
            entityType: 'session',
            entityId: $session->id,
        );

        SpecialActivityPublicCache::invalidate();

        return $session;
    }

    public function deleteSession(Session $session): void
    {
        $sessionId = $session->id;

        $session->delete();

        AuditLogger::log('special_activities.sessions.delete', entityType: 'session', entityId: $sessionId);

        SpecialActivityPublicCache::invalidate();
    }

    /**
     * Période de l'activité dérivée de ses sessions (jour minimal/maximal).
     *
     * @return array{start: int|null, end: int|null}
     */
    public function sessionPeriod(SpecialActivity $activity): array
    {
        $days = $activity->sessions()->distinct()->pluck('day_of_week')->map(fn ($day) => (int) $day);

        if ($days->isEmpty()) {
            return ['start' => null, 'end' => null];
        }

        return ['start' => $days->min(), 'end' => $days->max()];
    }
}
