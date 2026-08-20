<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Persistence;

use PDO;

final class MigrationRunner
{
    public function __construct(private readonly PDO $pdo, private readonly string $migrationRoot)
    {
    }

    /** @return list<string> */
    public function migrate(): array
    {
        $this->ensureLedger();
        $applied = $this->applied();
        $completed = [];
        $files = glob($this->migrationRoot . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $id = basename($file, '.sql');
            if (isset($applied[$id])) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Unable to read migration {$id}.");
            }
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $statement = $this->pdo->prepare('INSERT INTO schema_migrations (migration_id, applied_at) VALUES (:id, UTC_TIMESTAMP(6))');
                $statement->execute(['id' => $id]);
                if ($this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }
                $completed[] = $id;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }
        }
        return $completed;
    }

    private function ensureLedger(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration_id VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at DATETIME(6) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, true> */
    private function applied(): array
    {
        $rows = $this->pdo->query('SELECT migration_id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        return array_fill_keys(array_map('strval', $rows), true);
    }
}
