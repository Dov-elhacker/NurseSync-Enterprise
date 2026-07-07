<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sqlsrv_stubs.php';

// Fixes: destructive/mutating actions left no history — no record of who
// discharged a patient, terminated a nurse, or reset the census, or when.

function log_action($conn, string $actionType, string $entityType, ?int $entityId, string $details = ''): void {
    $performedBy = ns_current_user() ?: 'system';

    $query = "INSERT INTO AuditLog (action_type, entity_type, entity_id, performed_by, details) VALUES (?, ?, ?, ?, ?);";
    sqlsrv_query($conn, $query, array($actionType, $entityType, $entityId, $performedBy, $details));
}
