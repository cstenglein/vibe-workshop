# Todo-App

Eine einfache Webanwendung zum **Anlegen, Anzeigen und Löschen von Todos**.
Die Liste zeigt die neuesten Einträge zuerst, einschließlich Erstellungszeitpunkt.
Todo-Titel dürfen 1 bis 255 Zeichen lang sein.

Die App verwendet PHP 8.4 mit Apache und MySQL 8.4. Docker Compose startet beide
Dienste; MySQL speichert die Daten dauerhaft in einem Docker-Volume.

**Die App hat keinen Login. Alle Personen, die sie erreichen können, können Todos
anzeigen, anlegen und löschen.**

## Lokal starten

Voraussetzung: Docker mit Docker Compose. Alle folgenden Compose-Befehle im
Projektverzeichnis ausführen.

```bash
cp .env.example .env
chmod 600 .env
```

In `.env` die Platzhalter für `DB_PASSWORD` und `DB_ROOT_PASSWORD` durch zwei
unterschiedliche, zufällige Passwörter ersetzen. Danach starten:

```bash
docker compose up -d --build --wait
```

Die App ist unter **http://localhost:8080** erreichbar. Die Datenbank wird beim
Start eingerichtet; neue SQL-Migrationen werden automatisch ausgeführt.

## Konfiguration mit .env

Docker Compose liest `.env` im aktuellen Projektverzeichnis automatisch ein und
setzt die Werte in die `${…}`-Ausdrücke der `docker-compose.yml` ein. Ein zusätzlicher
`env_file:`-Eintrag ist dafür nicht erforderlich. Über `environment:` werden die
benötigten Datenbankwerte an die Container übergeben; die Datei selbst wird nicht
in das Image kopiert.

| Variable | Bedeutung |
| --- | --- |
| `DB_NAME` | Name der Datenbank, standardmäßig `todos` in der Vorlage |
| `DB_USER` | Datenbankbenutzer der App, standardmäßig `todos` in der Vorlage |
| `DB_PASSWORD` | Passwort des Datenbankbenutzers; muss gesetzt werden |
| `DB_ROOT_PASSWORD` | Separates MySQL-Root-Passwort; muss gesetzt werden |
| `APP_PORT` | Port auf dem Host; ohne Angabe wird `8080` verwendet |
| `CADDY_NETWORK` | Nur beim Docker-Caddy-Deployment: Name des bestehenden Caddy-Netzwerks |

Bereits gesetzte Shell-Variablen haben bei der Ersetzung Vorrang vor `.env`.
Eine andere Datei lässt sich explizit auswählen:

```bash
docker compose --env-file .env.production up -d --build --wait
```

`.env` enthält Zugangsdaten und gehört nicht ins Repository. Änderungen an den
Passwörtern in `.env` ändern nicht die Zugangsdaten einer bereits initialisierten
MySQL-Datenbank.

Details: [Variablen in Docker Compose](https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/).

## Deployment mit Caddy

Auf dem Server werden Docker Compose, die Projektdateien und eine ausgefüllte
`.env` benötigt. Die Einrichtung entspricht dem lokalen Start oben.

Die gewünschte Domain, etwa `todo.example.at`, muss per DNS auf den Server zeigen.
Für Caddy müssen die TCP-Ports **80 und 443** erreichbar sein. Caddy übernimmt die
Zertifikatsverwaltung und die Weiterleitung von HTTP auf HTTPS automatisch.
Siehe [Automatic HTTPS](https://caddyserver.com/docs/automatic-https).

Welche Proxy-Adresse verwendet wird, hängt davon ab, wo Caddy läuft:

| Caddy läuft … | Proxy-Ziel |
| --- | --- |
| direkt auf dem Host | `127.0.0.1:8080` bzw. der eingestellte `APP_PORT` |
| als Container im gemeinsamen Docker-Netzwerk | `todo-app:80` |

### Variante A: Caddy direkt auf dem Host

Die vorhandene `docker-compose.yml` veröffentlicht die App ausschließlich auf der
Loopback-Adresse des Hosts:

```yaml
ports:
  - "127.0.0.1:${APP_PORT:-8080}:80"
```

In die Caddy-Konfiguration, üblicherweise `/etc/caddy/Caddyfile`, eintragen:

```caddyfile
todo.example.at {
    reverse_proxy 127.0.0.1:8080
}
```

Falls `APP_PORT` geändert wurde, auch den Port im Caddyfile anpassen. Bei einer
Caddy-Installation als systemd-Dienst die Konfiguration prüfen und neu laden:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

### Variante B: Caddy in Docker

Caddy und `todo-app` müssen einem gemeinsamen Docker-Netzwerk angehören. Innerhalb
dieses Netzwerks verwendet Caddy den **Service-Namen `todo-app` und Container-Port
`80`**. `127.0.0.1` würde auf den Caddy-Container selbst zeigen; `APP_PORT` spielt
für diese Verbindung keine Rolle.

Die zusätzliche Datei `docker-compose.caddy.yml` verbindet die App mit dem
**bestehenden externen Netzwerk von Caddy**. In `.env` dessen Namen eintragen:

```dotenv
CADDY_NETWORK=caddy
```

`caddy` durch den tatsächlichen Netzwerknamen ersetzen. Das Netzwerk wird von
dieser App weder angelegt noch gelöscht. Caddy muss bereits damit verbunden sein.

Die App mit beiden Compose-Dateien starten:

```bash
docker compose -f docker-compose.yml -f docker-compose.caddy.yml up -d --build --wait
```

Auch bei Updates und anderen Compose-Befehlen für dieses Deployment beide
`-f`-Optionen verwenden. Der lokale Start mit `docker compose up -d --build --wait`
verwendet nur die Basisdatei und benötigt weder Caddy noch das externe Netzwerk.

Die App bleibt zusätzlich im internen Compose-Netzwerk mit `db` verbunden.
Die Datenbank wird nicht mit dem Caddy-Netzwerk verbunden. Die lokale
Portfreigabe auf `127.0.0.1` bleibt erhalten.

Der Caddy-Container muss die Host-Ports `80:80` und `443:443` veröffentlichen und
seine Zertifikatsdaten unter `/data` dauerhaft speichern. Das in den Container
eingebundene Caddyfile erhält folgenden Eintrag:

```caddyfile
todo.example.at {
    reverse_proxy todo-app:80
}
```

Nach dem Start der App im Caddy-Projektverzeichnis die Konfiguration
neu laden (Service-Name und Konfigurationspfad gegebenenfalls anpassen):

```bash
docker compose exec caddy caddy reload --config /etc/caddy/Caddyfile
```

Details: [Docker-Netzwerke](https://docs.docker.com/compose/how-tos/networking/) und
[Caddy betreiben](https://caddyserver.com/docs/running).

### Deployment prüfen

Die App unter **https://todo.example.at** öffnen. Der Health-Endpunkt prüft auch
die Datenbankverbindung:

```bash
curl --fail https://todo.example.at/health.php
```

Bei Erfolg antwortet er mit `ok`. Eine `APP_URL` in `.env` ist nicht erforderlich.
Caddy übermittelt das Browser-Protokoll an die App, die bei HTTPS sichere
Session-Cookies setzt.

## Betrieb und Updates

Nach dem Bereitstellen neuer Projektdateien im selben Verzeichnis starten:

```bash
docker compose up -d --build --wait
```

Status und Logs anzeigen:

```bash
docker compose ps
docker compose logs --tail=50
```

Die Daten bleiben bei Neustarts und `docker compose down` erhalten.
**`docker compose down --volumes` löscht auch die Datenbankdaten.**
Vor Datenbankänderungen ein Backup erstellen. Neue Migrationen als nächste
nummerierte SQL-Datei in `migrations/` hinzufügen und wiederholbar gestalten;
bereits ausgeführte Migrationen nicht verändern.

Ein optionaler GitHub-Actions-Workflow für Tests und SSH-Deployment ist in
[`.github/workflows/dev.yml`](.github/workflows/dev.yml) enthalten. Er benötigt die
Secrets `SSH_PRIVATE_KEY` und `SSH_KNOWN_HOSTS` sowie die Variablen `DEPLOY_HOST`,
`DEPLOY_USER`, `DEPLOY_PATH` und optional `DEPLOY_PORT` (Standard: `22`) sowie `APP_URL`.
`APP_URL` ist die öffentliche URL für den Health-Check nach dem Deployment. Ist sie
nicht gesetzt oder leer, wird dieser Check übersprungen; die internen Docker-Healthchecks
bleiben aktiv.

Der Workflow erwartet einen eigenen Deployment-Ordner mit vorbereiteter `.env`
und ohne Git-Checkout. Er synchronisiert die Projektdateien per rsync und entfernt
dabei sonstige Dateien; die `.env` bleibt erhalten. Caddy-Konfiguration und Backups
außerhalb dieses Ordners verwalten. Der Server benötigt SSH, rsync und Docker
Compose; der Deployment-Benutzer muss Docker ausführen dürfen. Den öffentlichen
Deployment-Schlüssel auf dem Server hinterlegen und den SSH-Hostschlüssel vor der
Übernahme in `SSH_KNOWN_HOSTS` über einen vertrauenswürdigen Zugang prüfen.

Nach erfolgreichen Tests auf `main` startet der Workflow die App mit dem
Compose-Projektnamen `todo-dev`. Der Workflow verwendet beide Compose-Dateien
und damit **Variante B mit Caddy in Docker**. Vor dem ersten Deployment auf dem Server `CADDY_NETWORK` in `.env`
auf den Namen des bestehenden, mit Caddy verbundenen Netzwerks setzen. Der
Workflow prüft die Compose-Konfiguration vor dem Start; ein fehlender Netzwerkname
oder ein nicht vorhandenes externes Netzwerk führt zum Abbruch des Deployments.

Für manuelle Befehle dieses Deployments ebenfalls denselben Projektnamen und
beide Compose-Dateien verwenden, damit dieselben Container, Netzwerke und Volumes
angesprochen werden, zum Beispiel:

```bash
docker compose -p todo-dev -f docker-compose.yml -f docker-compose.caddy.yml ps
```

Lokale Starts und die Tests verwenden weiterhin nur die Basisdatei und benötigen
kein Caddy-Netzwerk.

## Entwicklung und Tests

Änderungen auf einem eigenen Branch vornehmen und anschließend testen:

```bash
./scripts/test.sh
```

Das Skript benötigt Docker und Bash und verwendet ein separates Compose-Projekt
mit eigener Testdatenbank. Es prüft PHP-Syntax, Todo-Funktionen, Eingabevalidierung,
Formularschutz, Migrationen, Datenpersistenz und Datenbankausfälle. Die Testdaten
werden anschließend entfernt.

| Datei | Aufgabe |
| --- | --- |
| `app/public/index.php` | Todo-Liste und Formulare |
| `app/public/style.css` | Gestaltung der Oberfläche |
| `app/public/health.php` | Health-Check einschließlich Datenbankverbindung |
| `app/bootstrap.php` | Datenbankverbindung und gemeinsame Funktionen |
| `migrations/` | Nummerierte SQL-Migrationen |
| `scripts/migrate.php` | Führt Migrationen beim Containerstart aus |
| `Dockerfile`, `docker/` | PHP-Image und Startkonfiguration |
| `docker-compose.yml` | App- und Datenbankdienste für lokalen Start oder Caddy auf dem Host |
| `docker-compose.caddy.yml` | Optionale Verbindung zum bestehenden Caddy-Netzwerk |
| `tests/http_test.php` | HTTP-Funktionstests |
