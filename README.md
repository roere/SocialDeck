# Social Post

Lokale Social-Publishing-App mit Vanilla-JavaScript, PHP 8.3/Apache und MariaDB 11. LinkedIn OAuth ist implementiert; Instagram, Facebook und das Publishing bleiben providerneutrale Mock-Module.

## Sichere Struktur

Nur `public/` ist Apache-DocumentRoot. Backend, Datenbankdefinitionen, Docker-Dateien, Skripte, Tests, README und `.env` liegen außerhalb des Webroots. Apache blockiert zusätzlich Dotfiles und interne Dateitypen und setzt eine restriktive Content Security Policy.

## Lokaler Start

```bash
cp .env.example .env
```

Danach für `DB_PASSWORD`, `DB_ROOT_PASSWORD` und `INITIAL_ADMIN_PASSWORD` eigene starke lokale Werte setzen. Der Encryption Key besteht aus exakt 32 zufälligen Bytes in kanonischem Base64:

```bash
openssl rand -base64 32
```

Container starten und den Admin einmalig anlegen:

```bash
docker compose up -d --build --wait
docker compose exec web php scripts/seed-admin.php
```

Der Seed läuft ausschließlich explizit, überschreibt keine Benutzer und ist in `APP_ENV=production` deaktiviert. Das Admin-Passwort steht nur in `.env` und wird als `password_hash()` gespeichert.

App: `http://localhost:8080/`

## Sicherheit

- Serverseitige PHP-Session mit eigenem Cookie-Namen, HttpOnly, SameSite=Lax und Secure in Produktion
- Pre-Login-CSRF sowie CSRF-Schutz aller schreibenden authentifizierten Routen
- Aktuelle Rolle und `is_active` werden bei jedem geschützten Zugriff aus MariaDB geladen
- Provider- und SMTP-Secrets: AES-256-GCM, zufälliger Nonce, Auth-Tag und versioniertes `spsec:`-Format
- Strikte Base64-/32-Byte-Prüfung von `APP_ENCRYPTION_KEY`; kein Fallback
- Provider-Secrets werden nie an den Browser zurückgegeben
- Rechtstexte und Providerdaten werden über sichere DOM-APIs ausgegeben
- PDO mit nativen Prepared Statements

## Provider-Konfiguration

Der Admin-Bereich erzeugt LinkedIn-, Instagram- und Facebook-Karten aus einheitlichen Metadaten. Je Provider stehen Enabled, Client/App-ID, Secret, Redirect URI und Scopes bereit. Ein leeres Secret beim Update behält ein bestehendes Secret. Es werden keine persönlichen Netzwerkpasswörter erfasst.

## LinkedIn OAuth

Für LinkedIn wird OpenID Connect mit den Scopes `openid profile` verwendet. Nach dem Speichern und Aktivieren der Konfiguration startet **Mit LinkedIn verbinden** den serverseitig abgesicherten OAuth-Flow. Der Callback tauscht den Authorization Code ausschließlich serverseitig aus, liest die Identität aus LinkedIns UserInfo-Endpunkt und speichert Tokens AES-256-GCM-verschlüsselt in `social_accounts`. Der Admin zeigt Account-ID, Anzeigename, Ablaufzeit, Scopes und Status, aber niemals Tokens. **Verbindung trennen** entfernt die gespeicherten Tokens lokal und setzt den Status auf `disconnected`.

Lokale Redirect URI für die LinkedIn Developer App:

```text
http://localhost:8080/api/oauth/linkedin/callback
```

Automatische Tests verwenden ausschließlich einen lokalen LinkedIn-Mock. Sie kontaktieren LinkedIn nicht.

## Textbausteine

Der Admin-Reiter **Textbausteine** verwaltet normale und geschützte System-Textbausteine gemeinsam in MariaDB. Eine gruppierte, durchsuchbare Auswahl links steuert den zentralen Editor rechts; auf kleinen Displays stehen beide Bereiche untereinander. Jeder aktive Baustein kann über seinen eindeutigen technischen Schlüssel als `{{schluessel}}` in andere Bausteine eingefügt werden. Die zentrale serverseitige Auflösung unterstützt Verschachtelung, erkennt unbekannte Referenzen, Zyklen und zu große Tiefe. Systembausteine werden aus dem derzeit leeren, versionierten Anwendungskatalog kontrolliert angelegt; ihr Inhalt bleibt editierbar, ihr Schlüssel, Aktivstatus und Datensatz sind geschützt. Inhalte werden ausschließlich als Text ausgegeben; HTML wird nicht ausgeführt.

Bestehende lokale Datenbanken werden idempotent aktualisiert mit:

```bash
docker compose exec web php scripts/migrate.php
```

Dabei ergänzt `database/migrations/004-text-block-system-model.sql` die Spalte `is_system`. Bereits bearbeitete Inhalte werden vom Systembaustein-Katalog nicht überschrieben.

## Zentraler Beitragseditor

Der Basisbeitrag kann aktive Textbausteine als `{{schluessel}}` referenzieren. Die Vorschau sowie jede Mock-Veröffentlichung lösen diese Referenzen über die zentrale serverseitige Textbausteinlogik auf. Die Plattformauswahl wird aus der Provider Registry erzeugt. LinkedIn, Instagram und Facebook erhalten jeweils eine unabhängig bearbeitbare Fassung; spätere Änderungen am Basistext überschreiben diese Fassungen nur über die bewusste Aktion **Aus Basistext aktualisieren**.

Der lokale Entwurf speichert Basistext, Link, ausgewählte Provider, Plattformfassungen und Zeitstempel in `localStorage`. Vor jedem Mock-Publish wird jede Fassung separat aufgelöst und validiert. Ein Fehler bei einer Plattform erzeugt einen fehlgeschlagenen Job, ohne erfolgreiche Jobs anderer Plattformen zu verhindern. Es werden keine externen Social-Media-APIs aufgerufen.

Für den Editor stehen die öffentlichen, rein lokalen Endpunkte `GET /api/text-blocks` für aktive Bausteine und `POST /api/text-blocks/resolve` für die serverseitige Vorschauauflösung bereit.

## E-Mail-Konfiguration

Der vierte Admin-Reiter verwaltet die lokale SMTP-Konfiguration mit Aktivstatus, Host, Port, Verschlüsselung, Benutzername, Passwort, Absender und Reply-To. Lesen und Schreiben ist nur für angemeldete Admins erlaubt; Schreibzugriffe benötigen zusätzlich ein CSRF-Token. Das SMTP-Passwort wird AES-256-GCM-verschlüsselt gespeichert und von der API niemals ausgegeben. Ein leeres Passwortfeld behält das bereits gespeicherte Secret, ein neuer Wert ersetzt es. Die Konfiguration versendet weder beim Laden noch beim Speichern eine E-Mail; eine Testmail-Funktion ist bewusst nicht enthalten.

Bestehende lokale Datenbanken erhalten die Singleton-Tabelle `email_settings` mit:

```bash
docker compose exec web php scripts/migrate.php
```

## Tests

Die isolierte Suite startet eine eigene Compose-Umgebung auf Port 18080 und entfernt deren Testvolume anschließend:

```bash
bash tests/run-all.sh
```

Die Browser-Modultests sind in der Entwicklungsumgebung zusätzlich unter `http://localhost:8080/tests/test.html` erreichbar. Sie enthalten keine Zugangsdaten.

## Datenmodell

Das System ist derzeit bewusst Single-System/Single-Admin-orientiert; es wurde keine unnötige Mandantenstruktur ergänzt. Tabellen: `users`, `provider_configs`, `social_accounts`, `legal_settings`, `text_blocks`, `email_settings`. Für bestehende lokale Volumes liegen idempotente Strukturmigrationen unter `database/migrations/`.

## Noch nicht implementiert

- OAuth für Instagram oder Facebook
- externe Social-Media-API-Aufrufe
- echtes Publishing
- Medien-Uploads
- produktive Bereitstellung
