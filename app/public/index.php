<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    // Lokal HTTP; hinter Caddy enthält dieser Header das Protokoll des Browsers.
    'cookie_secure' => ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    'cookie_samesite' => 'Lax',
]);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$pdo = database();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        echo 'Ungültiges CSRF-Token. Bitte Seite neu laden.';
        return;
    }
    $action = $_POST['action'] ?? null;
    if ($action === 'create') {
        $title = $_POST['title'] ?? null;
        $title = is_string($title) && mb_check_encoding($title, 'UTF-8')
            ? preg_replace('/^\s+|\s+$/u', '', $title) : null;
        if ($title === null || $title === '' || mb_strlen($title, 'UTF-8') > 255) {
            $_SESSION['error'] = 'Bitte einen Titel mit 1 bis 255 Zeichen eingeben.';
        } else {
            $pdo->prepare('INSERT INTO todos (title) VALUES (?)')->execute([$title]);
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if (!is_string($id) || !ctype_digit($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $_SESSION['error'] = 'Ungültige Todo-ID.';
        } else {
            $pdo->prepare('DELETE FROM todos WHERE id = ?')->execute([$id]);
        }
    } else {
        $_SESSION['error'] = 'Unbekannte Aktion.';
    }
    header('Location: /', true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    return;
}
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
$todos = $pdo->query('SELECT id, title, created_at FROM todos ORDER BY id DESC')->fetchAll();
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meine Todos</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<main>
    <h1>Meine Todos</h1>
    <?php if ($error !== null): ?>
        <p role="alert"><?= escape($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/">
        <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="create">
        <label for="title">Neues Todo</label>
        <input id="title" name="title" required maxlength="255" autocomplete="off">
        <button type="submit">Hinzufügen</button>
    </form>
    <?php if ($todos === []): ?>
        <p>Noch keine Todos vorhanden.</p>
    <?php else: ?>
        <ul>
        <?php foreach ($todos as $todo): ?>
            <li>
                <span class="title"><?= escape($todo['title']) ?></span>
                <small><?= escape($todo['created_at']) ?> UTC</small>
                <form method="post" action="/">
                    <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= escape((string) $todo['id']) ?>">
                    <button type="submit" aria-label="Todo löschen: <?= escape($todo['title']) ?>">Löschen</button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
</body>
</html>
