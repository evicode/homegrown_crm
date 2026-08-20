<?php

declare(strict_types=1);

use Dreamsmith\Campaign\Authentication\AuthenticationService;
use Dreamsmith\Campaign\Persistence\Database;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$email = AuthenticationService::normalizeEmail((string) ($argv[1] ?? ''));
if ($email === null) {
    fwrite(STDERR, "Usage: php bin/create-owner.php owner@example.com\n");
    exit(2);
}
if (!function_exists('readline')) {
    fwrite(STDERR, "This command requires the readline extension for hidden password entry.\n");
    exit(2);
}
fwrite(STDOUT, 'Password (input may be visible in this terminal): ');
$password = readline();
if (!is_string($password) || strlen($password) < 12) {
    fwrite(STDERR, "Password must contain at least 12 characters.\n");
    exit(2);
}

/** @var array{dsn:string,user:string,password:string} $config */
$config = require $root . '/config/database.php';
$pdo = (new Database($config))->pdo();
$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count !== 0) {
    fwrite(STDERR, "An owner account already exists.\n");
    exit(1);
}
$statement = $pdo->prepare(
    'INSERT INTO users (email, password_hash, password_changed_at) VALUES (:email, :hash, UTC_TIMESTAMP(6))'
);
$statement->execute(['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Owner created.\n");
