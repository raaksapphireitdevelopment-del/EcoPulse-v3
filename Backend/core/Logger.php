<?php
declare(strict_types=1);

namespace EcoPulse\Core;

/**
 * Application & Audit Logger
 */
class Logger
{
    public static function audit(
        string $action,
        string $entityType,
        int $entityId,
        ?int $orgId = null,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $db = Database::getInstance();
            $db->insert('audit_logs', [
                'user_id' => $userId ?? Auth::id(),
                'organization_id' => $orgId ?? Session::getActiveOrgId(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
                'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Silently fall back to file log if database audit fails
            self::error('Audit logging failed: ' . $e->getMessage());
        }
    }

    public static function error(string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] ERROR: %s %s\n",
            date('Y-m-d H:i:s'),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        @file_put_contents($logFile, $entry, FILE_APPEND);
    }

    public static function warning(string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] WARNING: %s %s\n",
            date('Y-m-d H:i:s'),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        @file_put_contents($logFile, $entry, FILE_APPEND);
    }

    public static function info(string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] INFO: %s %s\n",
            date('Y-m-d H:i:s'),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}

