<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'dreamsmith_campaign';

return [
    'dsn' => getenv('DB_DSN') ?: "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
];
