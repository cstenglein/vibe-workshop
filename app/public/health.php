<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
$pdo = database();
$pdo->query('SELECT id, title, created_at FROM todos LIMIT 1');
echo "ok\n";
