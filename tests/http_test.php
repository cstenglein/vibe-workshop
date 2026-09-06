<?php

declare(strict_types=1);

// Läuft im PHP-Container und ruft die App wie ein Browser auf.
final class Browser
{
    private string $cookie = '';

    public function request(string $path = '/', ?array $fields = null, array $extraHeaders = []): array
    {
        $context = stream_context_create(['http' => [
            'method' => $fields === null ? 'GET' : 'POST',
            'header' => "Cookie: {$this->cookie}\r\nContent-Type: application/x-www-form-urlencoded\r\n" . implode("\r\n", $extraHeaders),
            'content' => $fields === null ? '' : http_build_query($fields),
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 15,
        ]]);
        $body = file_get_contents('http://127.0.0.1' . $path, false, $context);
        check($body !== false, 'HTTP-Anfrage fehlgeschlagen');
        $headers = $http_response_header;
        preg_match('/HTTP\/\S+ (\d+)/', $headers[0], $status);
        foreach ($headers as $header) {
            if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $match)) {
                $this->cookie = $match[1];
            }
        }
        return [(int) $status[1], $body, $headers];
    }

    public function page(): string
    {
        [$status, $body] = $this->request();
        check($status === 200, 'Seite erreichbar');
        return $body;
    }

    public function titles(): array
    {
        preg_match_all('/<span class="title">(.*?)<\/span>/s', $this->page(), $matches);
        return array_map(static fn(string $title): string => html_entity_decode($title, ENT_QUOTES, 'UTF-8'), $matches[1]);
    }

    public function post(array $fields, int $expected = 303): void
    {
        [$status, , $headers] = $this->request('/', $fields);
        check($status === $expected, "POST liefert HTTP $expected");
        if ($expected === 303) {
            check(in_array('Location: /', $headers, true), 'Weiterleitung zur Liste');
        }
    }
}

function check(bool $success, string $message): void
{
    if (!$success) {
        throw new RuntimeException('Test fehlgeschlagen: ' . $message);
    }
}

$browser = new Browser();
$mode = $argv[1] ?? 'exercise';
if ($mode === 'unavailable') {
    [$status, $body] = $browser->request('/health.php');
    check($status === 503 && $body === 'Dienst vorübergehend nicht verfügbar.', 'Healthcheck bei DB-Ausfall');
    echo "Healthcheck bei DB-Ausfall: OK\n";
    exit;
}
[$status, $body] = $browser->request('/health.php');
check($status === 200 && $body === "ok\n", 'Healthcheck');
if ($mode === 'persistence') {
    check($browser->titles() === ['Bleibt nach Neustart'], 'Persistenz nach Neuerstellung');
    echo "Persistenz nach Neuerstellung: OK\n";
    exit;
}

foreach (['' => false, 'http' => false, 'https' => true] as $protocol => $secure) {
    $headers = $protocol === '' ? [] : ['X-Forwarded-Proto: ' . $protocol];
    [$status, , $responseHeaders] = (new Browser())->request('/', null, $headers);
    check($status === 200, 'Seite lokal und hinter Proxy erreichbar');
    $cookies = array_values(array_filter($responseHeaders, static fn(string $header): bool => str_starts_with(strtolower($header), 'set-cookie:')));
    check(count($cookies) === 1, 'Session-Cookie gesetzt');
    check((preg_match('/;\s*secure(?:;|$)/i', $cookies[0]) === 1) === $secure, 'Secure-Cookie nur bei HTTPS über Proxy');
}
echo "Session-Cookies lokal und mit Proxy-Header: OK\n";

preg_match('/name="csrf" value="([a-f0-9]+)"/', $browser->page(), $match);
$token = $match[1];
check($browser->titles() === [], 'Leere Testdatenbank');
foreach (['create', 'delete'] as $action) {
    foreach ([null, 'wrong'] as $csrf) {
        $browser->post(['action' => $action, 'title' => 'Verboten', 'id' => '1', 'csrf' => $csrf], 403);
    }
}
check($browser->titles() === [], 'CSRF verhindert Änderungen');
foreach (['', '   ', "\u{2003}", str_repeat('ä', 256), ['array']] as $title) {
    $browser->post(['action' => 'create', 'title' => $title, 'csrf' => $token]);
    check(str_contains($browser->page(), '1 bis 255 Zeichen'), 'Fehlermeldung bei ungültigem Titel');
    check($browser->titles() === [], 'Ungültiger Titel wird nicht gespeichert');
}
$values = ['Grüße 🌍 日本語', '<script>alert("x")</script> & Test', str_repeat('ä', 255)];
foreach ($values as $title) {
    $browser->post(['action' => 'create', 'title' => $title, 'csrf' => $token]);
}
check($browser->titles() === array_reverse($values), 'Anlegen, Unicode und Reihenfolge');
check(!str_contains($browser->page(), '<script>') && str_contains($browser->page(), '&lt;script&gt;'), 'HTML-Escaping');
preg_match_all('/name="id" value="(\d+)"/', $browser->page(), $matches);
$ids = $matches[1];
foreach ([null, 'wrong'] as $csrf) {
    $browser->post(['action' => 'delete', 'id' => $ids[0], 'csrf' => $csrf], 403);
}
foreach (['0', '-1', '1 OR 1=1', '999999999999999999999999'] as $id) {
    $browser->post(['action' => 'delete', 'id' => $id, 'csrf' => $token]);
}
check(count($browser->titles()) === 3, 'Ungültiges Löschen verändert nichts');
foreach ($ids as $id) {
    $browser->post(['action' => 'delete', 'id' => $id, 'csrf' => $token]);
}
check($browser->titles() === [], 'Löschen');
$browser->post(['action' => 'create', 'title' => 'Bleibt nach Neustart', 'csrf' => $token]);
check($browser->titles() === ['Bleibt nach Neustart'], 'Todo für Neustart angelegt');
echo "Anlegen, Anzeigen, Löschen, Unicode, Validierung, Escaping und CSRF: OK\n";
