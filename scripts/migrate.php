<?php

declare(strict_types=1);

require '/var/www/app/bootstrap.php';
$pdo = database();
$pdo->exec('SET SESSION lock_wait_timeout = 15');
if ((int) $pdo->query("SELECT GET_LOCK('todo_migrations', 30)")->fetchColumn() !== 1) {
    throw new RuntimeException('Migration lock unavailable.');
}
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) NOT NULL PRIMARY KEY,
        checksum CHAR(64) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $files = glob('/var/www/migrations/[0-9]*.sql');
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        $version = basename($file);
        $checksum = hash_file('sha256', $file);
        $query = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
        $query->execute([$version]);
        $previous = $query->fetchColumn();
        if ($previous !== false) {
            if ($previous !== $checksum) {
                throw new RuntimeException('Applied migration changed: ' . $version);
            }
            continue;
        }
        // MySQL DDL commits implicitly. Each migration must be safe to retry.
        $pdo->exec(file_get_contents($file));
        $pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)')
            ->execute([$version, $checksum]);
        echo 'Applied ' . $version . PHP_EOL;
    }
} finally {
    $pdo->query("SELECT RELEASE_LOCK('todo_migrations')");
}
