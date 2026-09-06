# Todo-App

Die App kann Todos **anzeigen, anlegen und löschen**. PHP zeigt die Webseite,
MySQL speichert die Todos, Docker startet beides.

## Starten

Beim ersten Mal:

```bash
cp .env.example .env
```

In `.env` die beiden Passwort-Platzhalter durch eigene Passwörter ersetzen. Dann:

```bash
docker compose up -d --build --wait
```

Im Browser **http://localhost:8080** öffnen. Läuft Docker auf dem VPS, wird die
App über die vorbereitete Domain geöffnet.

## Welche Datei macht was?

| Datei | Aufgabe |
| --- | --- |
| `app/public/index.php` | Todo-Liste und Formulare zum Anlegen und Löschen |
| `app/public/style.css` | Aussehen der Seite |
| `app/bootstrap.php` | Verbindung zur Datenbank |
| `migrations/001_create_todos.sql` | Legt die Todo-Tabelle an |
| `scripts/migrate.php` | Führt neue Datenbankänderungen beim Start aus |
| `Dockerfile` | Baut den PHP-Container; `docker/` enthält dessen Starteinstellungen |
| `docker-compose.yml` | Startet PHP und MySQL zusammen |
| `.env` | Eigene Einstellungen und Passwörter – bleibt außerhalb von Git |
| `scripts/test.sh` | Startet eine separate Test-App und räumt sie anschließend auf |
| `tests/http_test.php` | Prüft die App automatisch mit PHP |
| `.github/workflows/dev.yml` | Testet Änderungen und veröffentlicht sie auf dem Dev-VPS |

## Nach einer Änderung

```bash
./scripts/test.sh
```

Benötigt nur Docker und Bash. PHP läuft bereits im Container; eine weitere
Programmiersprache muss nicht installiert werden. Die Tests prüfen Anlegen,
Anzeigen, Löschen, Eingaben, Formularschutz und den Erhalt der Daten nach Neustart.
Die Todos der normalen App werden dabei nicht verändert.

Um die geänderte App selbst anzusehen:

```bash
docker compose up -d --build --wait
```

Für den Workshop gilt:

**Eigener Branch → Änderung → Tests → Commit → Push → Pull Request → Merge.**

GitHub prüft jeden Pull Request. Nach einem Merge nach `main` testet GitHub erneut,
kopiert die Dateien auf den VPS und startet dort die neue Version. Alle Schritte
stehen direkt in `.github/workflows/dev.yml`. Unter **GitHub → Actions** sieht man,
ob sie erfolgreich waren. Deployments laufen nacheinander.

## Einmalige Vorbereitung durch den Workshop-Leiter

Diese Einrichtung erfolgt **vor dem Workshop**. Der Teilnehmer braucht einen
fertigen VPS-Zugang und eine funktionierende Domain.

Der VPS benötigt Docker Compose, SSH, rsync und Caddy. Der SSH-Benutzer muss Docker
verwenden dürfen. Beispielwerte durch die eigenen Werte ersetzen:

| Einstellung | Beispiel |
| --- | --- |
| VPS | `vps.example.at` |
| SSH-Benutzer | `andreas` |
| Deployment-Ordner | `/home/andreas/deploy/todo-dev` |
| Domain | `dev.example.at` |

Auf dem VPS einen **eigenen, leeren Deployment-Ordner** anlegen und die Vorlage
`.env.example` dort als `.env` speichern. Passwörter setzen und Folgendes eintragen:

```dotenv
APP_PORT=8080
```

Die Datei mit `chmod 600 .env` schützen. Dieser Ordner ist getrennt vom
Entwicklungsprojekt: GitHub ersetzt dort die Dateien, erhält aber die `.env`.
Dort deshalb keine anderen Dateien oder Backups ablegen.

In Caddy eintragen und die Konfiguration neu laden:

```caddyfile
dev.example.at {
    reverse_proxy 127.0.0.1:8080
}
```

DNS muss auf den VPS zeigen. Nur Caddy veröffentlicht die App; MySQL hat keinen
öffentlichen Port. Die App hat keinen Login – alle Besucher können Todos ändern.

Die App läuft lokal über HTTP und über Caddy mit HTTPS. Caddy übermittelt das
Browser-Protokoll automatisch; die App setzt damit sichere Session-Cookies.
Eine `APP_URL` in `.env` ist nicht nötig. Die unten genannte GitHub-Variable
`APP_URL` dient nur zur Erreichbarkeitsprüfung nach dem Deployment.

Einen eigenen SSH-Schlüssel für GitHub anlegen. Den öffentlichen Schlüssel beim
VPS-Benutzer hinterlegen. Den Hostschlüssel des VPS über dessen Konsole prüfen;
einen ungeprüften `ssh-keyscan`-Wert nicht einfach übernehmen.

Unter **GitHub → Settings → Secrets and variables → Actions** eintragen:

| Art | Name | Wert |
| --- | --- | --- |
| Secret | `SSH_PRIVATE_KEY` | Privater Deployment-Schlüssel ohne Passphrase |
| Secret | `SSH_KNOWN_HOSTS` | Geprüfte Known-Hosts-Zeile des VPS |
| Variable | `DEPLOY_HOST` | `vps.example.at` |
| Variable | `DEPLOY_USER` | `andreas` |
| Variable | `DEPLOY_PORT` | `22` (optional) |
| Variable | `DEPLOY_PATH` | `/home/andreas/deploy/todo-dev` |
| Variable | `APP_URL` | `https://dev.example.at` |

Für `main` Pull Requests und den erfolgreichen Check **Tests** vorschreiben.
Danach einen ersten Merge durchführen und Domain sowie GitHub Actions prüfen.
Wenn Entwicklungsprojekt und Deployment auf demselben VPS laufen, im
Entwicklungsprojekt einen anderen `APP_PORT`, etwa `8081`, verwenden.

## Wenn etwas nicht funktioniert

```bash
docker compose ps
docker compose logs --tail=50
```

Für die von GitHub gestartete App: im Deployment-Ordner dieselben Befehle mit
`docker compose -p todo-dev` verwenden.

Die Daten bleiben bei einem Neustart erhalten. **`down --volumes` löscht die Daten.**
Neue Datenbankänderungen als nächste SQL-Datei hinzufügen; bereits ausgeführte
Migrationen nicht ändern. Vor Schemaänderungen Daten sichern. Geänderte Passwörter
in `.env` ändern die Zugangsdaten einer bereits vorhandenen Datenbank nicht.
