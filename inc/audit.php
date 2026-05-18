<?php
/**
 * IMPACT365 — Audit Log
 * Records governance-relevant actions for traceability.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function audit(string $action, ?string $entity = null, $entityId = null, ?string $details = null): void
{
    try {
        db_insert('audit_logs', [
            'user_id'   => $_SESSION['user']['id'] ?? null,
            'role'      => $_SESSION['user']['role'] ?? null,
            'action'    => substr($action, 0, 80),
            'entity'    => $entity ? substr($entity, 0, 60) : null,
            'entity_id' => $entityId !== null ? substr((string) $entityId, 0, 40) : null,
            'details'   => $details ? substr($details, 0, 500) : null,
            'ip'        => client_ip(),
        ]);
    } catch (Throwable $e) {
        // Auditing must never break the primary action.
        error_log('[IMPACT365] audit failed: ' . $e->getMessage());
    }
}
