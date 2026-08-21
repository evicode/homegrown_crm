<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Persistence;

use PDO;
use PDOException;
use Throwable;

final class TransactionManager
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(callable $operation, bool $retrySafe = false, int $maxAttempts = 1): mixed
    {
        if ($maxAttempts < 1 || (!$retrySafe && $maxAttempts !== 1)) {
            throw new \InvalidArgumentException('Retries require an explicitly retry-safe operation.');
        }

        if ($this->pdo->inTransaction()) {
            $savepoint = 'nested_' . bin2hex(random_bytes(8));
            $this->pdo->exec('SAVEPOINT ' . $savepoint);
            try {
                $result = $operation($this->pdo);
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                return $result;
            } catch (Throwable $exception) {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                throw $exception;
            }
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->pdo->beginTransaction();
            try {
                $result = $operation($this->pdo);
                $this->pdo->commit();
                return $result;
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                if (!$retrySafe || $attempt === $maxAttempts || !$this->isRetryable($exception)) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Transaction attempts exhausted unexpectedly.');
    }

    private function isRetryable(Throwable $exception): bool
    {
        if (!$exception instanceof PDOException) {
            return false;
        }
        $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;
        return in_array($driverCode, [1205, 1213], true) || $exception->getCode() === '40001';
    }
}
