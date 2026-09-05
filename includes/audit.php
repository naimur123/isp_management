<?php
/**
 * Activity / audit logging.
 */

function log_activity(string $action, string $module, $recordId = null, ?string $description = null): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id, user_name, action, module, record_id, description, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            current_user_id(),
            current_user()['full_name'] ?? current_user()['username'] ?? 'System',
            $action,
            $module,
            $recordId !== null ? (string) $recordId : null,
            $description,
            client_ip(),
        ]);
    } catch (Throwable $e) {
        // Never let audit logging break the request.
    }
}
