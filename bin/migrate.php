<?php

declare(strict_types=1);

use Dreamsmith\Campaign\Persistence\Database;
use Dreamsmith\Campaign\Persistence\MigrationRunner;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

/** @var array{dsn:string,user:string,password:string} $config */
$config = require $root . '/config/database.php';
$runner = new MigrationRunner((new Database($config))->pdo(), $root . '/database/migrations');
$applied = $runner->migrate();

echo $applied === [] ? "No pending migrations.\n" : 'Applied: ' . implode(', ', $applied) . PHP_EOL;
