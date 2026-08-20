<?php

declare(strict_types=1);

namespace Dreamsmith\Campaign\Persistence;

use PDO;

final class Database
{
    private ?PDO $connection = null;

    /** @param array{dsn:string,user:string,password:string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        return $this->connection ??= new PDO($this->config['dsn'], $this->config['user'], $this->config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function transaction(callable $operation): mixed
    {
        return (new TransactionManager($this->pdo()))->run($operation);
    }
}
