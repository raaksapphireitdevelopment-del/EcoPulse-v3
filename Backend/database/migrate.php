<?php
declare(strict_types=1);

/**
 * EcoPulse Database Migration CLI Runner
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/MigrationRunner.php';

use EcoPulse\Database\MigrationRunner;

echo "=== EcoPulse MVP V3 Migration Runner ===\n";

try {
    $runner = new MigrationRunner();
    $migrationsDir = __DIR__ . '/migrations';
    $executed = $runner->runPending($migrationsDir);

    if (empty($executed)) {
        echo "[INFO] Database schema is already up to date. No pending migrations.\n";
    } else {
        foreach ($executed as $mig) {
            echo " [APPLIED] {$mig}\n";
        }
        echo "[SUCCESS] Successfully applied " . count($executed) . " migration(s).\n";
    }
} catch (Throwable $e) {
    echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
