<?php
declare(strict_types=1);

namespace EcoPulse\Database;

use EcoPulse\Core\Database;
use PDO;
use Throwable;

class MigrationRunner
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getPdo();
        $this->ensureMigrationTable();
    }

    /**
     * Ensure schema_migrations table exists for version tracking
     */
    public function ensureMigrationTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration_name` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT UNSIGNED NOT NULL DEFAULT 1,
            `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $this->pdo->exec($sql);

        // If migration table was just created, baseline existing migrations that were already applied
        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM `schema_migrations`")->fetchColumn();
        if ($count === 0) {
            $existing = [
                '05_carbon_calculations_phase3.sql',
                '05_phase3_calculation_engine.sql',
                '06_analytics_and_reduction_phase4.sql',
                '07_phase5_projects_evidence_readiness.sql',
            ];
            $stmt = $this->pdo->prepare("INSERT INTO `schema_migrations` (`migration_name`, `batch`, `applied_at`) VALUES (:name, 1, NOW())");
            foreach ($existing as $mig) {
                try {
                    $stmt->execute([':name' => $mig]);
                } catch (\Throwable $e) {
                    // Ignore duplicate
                }
            }
        }
    }

    /**
     * Get list of already applied migrations
     */
    public function getAppliedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT `migration_name` FROM `schema_migrations` ORDER BY `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Get next batch number
     */
    public function getNextBatch(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(`batch`) as max_batch FROM `schema_migrations`");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int)($res['max_batch'] ?? 0)) + 1;
    }

    /**
     * Record a migration as applied
     */
    public function recordMigration(string $migrationName, int $batch): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO `schema_migrations` (`migration_name`, `batch`, `applied_at`) VALUES (:name, :batch, NOW())");
        $stmt->execute([':name' => $migrationName, ':batch' => $batch]);
    }

    /**
     * Run all pending migrations in directory
     */
    public function runPending(string $migrationsDir): array
    {
        $this->ensureMigrationTable();

        // Prevent concurrent migration execution with database advisory lock
        $lockStmt = $this->pdo->query("SELECT GET_LOCK('ecopulse_migration_lock', 10) as has_lock");
        $lockRes = $lockStmt ? $lockStmt->fetch(PDO::FETCH_ASSOC) : null;
        if (empty($lockRes['has_lock'])) {
            throw new \RuntimeException("Could not acquire migration lock. Another migration process is currently running.");
        }

        try {
            $applied = $this->getAppliedMigrations();
            $files = glob(rtrim($migrationsDir, '/\\') . '/*.sql');
            sort($files);

            $executed = [];
            $batch = $this->getNextBatch();

            foreach ($files as $file) {
                $name = basename($file);
                if (in_array($name, $applied, true)) {
                    continue; // Already applied safely
                }

                $sql = file_get_contents($file);
                if ($sql === false || trim($sql) === '') {
                    continue;
                }

                $this->executeSqlStatements($sql);
                $this->recordMigration($name, $batch);
                $executed[] = $name;
            }

            return $executed;
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('ecopulse_migration_lock')");
        }
    }

    /**
     * Execute SQL statements handling multi-queries safely
     */
    public function executeSqlStatements(string $sql): void
    {
        // Remove comments
        $lines = explode("\n", $sql);
        $cleanLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $cleanLines[] = $line;
        }
        $cleanedSql = implode("\n", $cleanLines);

        // Split statements by semicolon followed by newline or end of string
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*[\r\n]+/', $cleanedSql)),
            fn($stmt) => !empty($stmt)
        );

        foreach ($statements as $statement) {
            if (trim($statement) === '' || trim($statement) === ';') {
                continue;
            }
            try {
                $this->pdo->exec($statement);
            } catch (Throwable $e) {
                // If it's a harmless duplicate column, already exists, or already dropped error, continue
                $msg = $e->getMessage();
                if (
                    str_contains($msg, 'Duplicate column') ||
                    str_contains($msg, 'already exists') ||
                    str_contains($msg, 'Duplicate key') ||
                    str_contains($msg, "Can't DROP") ||
                    str_contains($msg, 'check that column/key exists')
                ) {
                    continue;
                }
                throw $e;
            }
        }
    }
}
