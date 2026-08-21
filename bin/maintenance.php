<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$config = require $root . '/config/app.php';
$dryRun = in_array('--dry-run', $argv, true);
$cutoff = time() - ((int) $config['runtime_retention_hours'] * 3600);
$targets = [$root . '/var/tmp' => null, $root . '/var/log' => '/\.log$/i'];
$removed = 0;

foreach ($targets as $directory => $pattern) {
    $resolved = realpath($directory);
    if ($resolved === false || !str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $root . '/var/'))) {
        throw new RuntimeException('Maintenance target is outside the runtime directory.');
    }
    foreach (new DirectoryIterator($resolved) as $file) {
        if ($file->isDot() || !$file->isFile() || $file->getMTime() >= $cutoff) continue;
        if ($pattern !== null && preg_match($pattern, $file->getFilename()) !== 1) continue;
        $path = $file->getPathname();
        echo ($dryRun ? 'Would remove: ' : 'Removed: ') . $path . PHP_EOL;
        if (!$dryRun && !unlink($path)) throw new RuntimeException('Unable to remove expired runtime file.');
        $removed++;
    }
}
echo ($dryRun ? 'Would remove ' : 'Removed ') . $removed . " expired runtime files.\n";
