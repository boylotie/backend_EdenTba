<?php

namespace App\Shared\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AuditLogger
{
    /**
     * Enregistre une action sensible dans le journal d'audit (base de données).
     *
     * En cas d'échec d'écriture, l'action bascule vers le canal de secours
     * `audit` (fichier) afin de ne jamais perdre la traçabilité.
     *
     * @param  array<string, mixed>  $context
     */
    public static function log(
        string $action,
        array $context = [],
        ?int $actorId = null,
        ?string $entityType = null,
        string|int|null $entityId = null,
    ): void {
        try {
            AuditLog::create([
                'actor_id' => $actorId ?? auth()->user()?->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'context' => $context,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (Throwable $exception) {
            Log::channel('audit')->error('audit_logs.fallback', [
                'action' => $action,
                'context' => $context,
                'actor_id' => $actorId ?? auth()->user()?->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
