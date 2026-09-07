# Mehrere Provider Apps

## Analyse des bisherigen Modells

Bisher war `provider_configs.provider_id` eindeutig und enthielt genau einen Credential-Satz. `linkedInConfig()` lud diese globale Zeile; OAuth-Start und Callback verwendeten sie unabhängig vom konkreten Versuch. `social_accounts` war auf `(provider_id, external_account_id)` eindeutig, sodass eine zweite Developer-App dasselbe Mitglied überschrieben hätte. Die Admin-UI zeigte ein Konfigurationsformular und das erste verbundene Konto. Organization-Sync nahm das letzte verbundene Konto. Kampagnen prüften die providerweite Aktivierung statt einer App. Posting und Medien waren bereits an einen ausgewählten Channel und dessen Account gebunden. Eine Event-Funktion ist im Projekt nicht vorhanden.

## Datenmodell und Migration

`012-provider-apps.sql` führt die providerneutrale Tabelle `provider_apps` ein: ID, Provider, Name, Client ID, verschlüsseltes Secret, Redirect URI, konfigurierte Scopes, Aktivierung, optionaler beschreibender Zweck und Zeitstempel. `legacy_config_id` dient ausschließlich der wiederholbaren Migration.

Bestehende Konfigurationen werden automatisch als erste Standard-App übernommen. Die Migration kopiert den vorhandenen Secret-Ciphertext unverändert, ordnet bestehende Accounts zu und verändert deren IDs, Access-/Refresh-Token, Laufzeit oder gewährte Scopes nicht. Channels, Posts und Medien behalten ihre Accountreferenzen. Für alte Accounts ohne Konfigurationszeile wird eine deaktivierte Standard-App angelegt.

`social_accounts.provider_app_id` ist verpflichtend. Der zusammengesetzte Fremdschlüssel `(provider_app_id, provider_id)` verhindert eine providerfremde Zuordnung. Die neue Eindeutigkeit `(provider_app_id, external_account_id)` erlaubt dasselbe Mitglied pro App. App-Löschung ist durch Fremdschlüssel und API-Prüfung geschützt; es gibt keine Account-/Token-Cascade beim Löschen einer App.

`provider_configs` bleibt für providerweite Einstellungen und als Migrationsnachweis bestehen. Nach der Übernahme sind die alten Credential-/Scope-Spalten leer und werden von der Anwendung nicht mehr beschrieben. Credentials werden ausschließlich in `provider_apps` gespeichert. Der Migrationsmarker verhindert, dass gelöschte Standard-Apps bei einer späteren Wiederholung neu entstehen. Das vorhandene Bootstrap-Schema bildet weiterhin den Ausgangsstand ab; der Migrationsrunner führt anschließend auch Migration 012 aus. Die Migration wurde nur auf isolierten Testdaten ausgeführt, nicht auf der produktiven Datenbank.

## Admin und API

LinkedIn zeigt mehrere App-Karten, ein Formular für Anlage/Bearbeitung sowie getrennte Konten, Grants, Channels und OAuth-Ergebnisse. Name, Credentials, Redirect URI, Scopes, enabled und purpose sind editierbar. Der Zweck beeinflusst keine Auswahlentscheidung. Ein leeres Secretfeld erhält das gespeicherte Secret; kein GET liefert es zurück. Die Client ID einer App mit bestehenden Accounts ist gegen einen Austausch geschützt, damit alte Tokens nicht einer anderen Developer-App zugeschrieben werden.

Providerneutrale Routen:

- `GET/POST /api/admin/providers/{provider}/apps`
- `GET/PUT/DELETE /api/admin/providers/{provider}/apps/{id}`
- `POST .../{id}/connect`, `POST .../{id}/disconnect`, `POST .../{id}/channels-sync` (zunächst LinkedIn)

Disconnect verlangt zusätzlich eine konkrete Account-ID und prüft deren App-Zugehörigkeit. Historische, bereits getrennte Accounts bleiben erhalten und verhindern weiterhin das Löschen einer App. Unbenutzte Apps können gelöscht werden. Alle Routen benötigen Adminrechte; schreibende Aktionen zusätzlich CSRF.

Die bisherigen globalen Konfigurations-/Connect-Routen bleiben als Übergang für genau eine App nutzbar. Bei mehreren Apps lehnen sie mit `APP_REQUIRED` ab. Sie wählen niemals still eine App für Credential-Änderungen oder OAuth aus. Andere Provider können das neue Datenmodell und CRUD verwenden; deren OAuth-Adapter bleiben unverändert nicht implementiert.

## OAuth, Tokens und Scopes

OAuth-Start liest ausschließlich die gewählte gespeicherte App. Browserseitig mitgeschickte Client IDs oder Scopes werden ignoriert. Der serverseitige State enthält Provider, App-ID, App-Name, angefragten Scope-Snapshot, Erstellungszeit und einen internen Fingerabdruck der Credentials/Redirect URI. Die bestehende TTL und der Einmalverbrauch bleiben erhalten. Alte States ohne App-Zuordnung müssen neu gestartet werden.

Der Callback verwendet ausschließlich die App aus dem konsumierten State. Queryparameter zur App-Auswahl sind wirkungslos. Ändern sich Client ID, Secret oder Redirect URI während des Flows, wird der Versuch verworfen. Eine reine Scope-Konfigurationsänderung ersetzt nicht den ursprünglichen Request-Snapshot. Vor der Account-Speicherung werden App und Credential-Fingerabdruck unter einer Datenbanksperre erneut geprüft.

Jeder Account erhält eigene verschlüsselte Access-/Refresh-Tokens, Laufzeit und Grants aus seiner Tokenantwort. Es gibt weder globale Tokens noch eine zusammengeführte Grant-Liste. Ein Fehler bei App B verändert weder Accountdaten noch OAuth-Ergebnis von App A. Fehler nennen die App und zeigen ihren konkreten Scope-Snapshot; keine spekulative Einzel-Scope-Diagnose. Die bestehende lokale Ergebnisanzeige bleibt sitzungsgebunden und eine Stunde sichtbar. Sie garantiert keine externe Token-Gültigkeit.

Mehrere Apps können denselben Callback verwenden, wenn die URL in jeder App registriert ist. Die offizielle LinkedIn-Dokumentation bindet Redirect URI und Code an die jeweilige Client-App; eine globale Exklusivität der URL ist dort nicht vorgesehen. Die App-Zuordnung übernimmt unser serverseitiger State. Referenz: [LinkedIn Authorization Code Flow](https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow).

Der vorhandene Profilabruf setzt weiterhin explizit `openid` und `profile` voraus. Diese Scopes werden nicht automatisch ergänzt. Apps ohne diese Rechte benötigen einen anderen, gesondert zu implementierenden Identitätsabruf; die UI darf keine funktionsfähige Verbindung ohne bestätigte Mitgliedsidentität behaupten.

## Capability-Auswahl und Nutzung

Der gemeinsame Resolver wertet aktive App-Verbindungen, Accountstatus, Tokenvorhandensein, Laufzeit und tatsächlich gewährte Scopes aus. Die feste Reihenfolge lautet App-ID aufsteigend, anschließend Account-ID aufsteigend. So bleibt die migrierte erste App bevorzugt, sofern sie die benötigten Rechte besitzt. App-Name und Zweck sind irrelevant; konfigurierte Scopes verleihen keine Capability.

Die Admin-Matrix zeigt Berechtigung und bereitstellende App getrennt für Posting, Events lesen/verwalten, Kommentare, Feed-Leserecht, bekannte Posts und Organization Discovery/Publishing. Discovery akzeptiert weiterhin die bestehende Alternative `rw_organization_admin`; bekannte Posts verlangen weiterhin beide vorhandenen Leserechte. Das Vorhandensein eines Scopes ersetzt keine API-Produktfreigabe, Ressourcenzugriffsrechte oder implementierte Produktoberfläche.

Persönliches Posting verwendet den Token des explizit gewählten aktiven Channels. Ohne mitgeschickte Channel-ID ermittelt der Server deterministisch einen persönlichen Channel mit `w_member_social`. Medien und Postinghistorie bleiben an genau diesen Account gebunden. Im Editor sind App-Namen an den Channels sichtbar, deaktivierte Apps liefern keine veröffentlichungsfähigen Channels.

Kampagnen wählen beim Speichern die erste geeignete Kommentar-Verbindung und halten sie im Ziel sowie in der Abschlussfreigabe fest. Nach der Freigabe wird nicht auf eine andere App umgeschaltet. Fehlt eine passende Verbindung, bleibt der manuelle Fallback. Beim Aktualisieren bekannter Posts wird die passende Leseverbindung unabhängig von der Kommentar-App gewählt. Der bestehende allgemeine Netzwerkfeed wird dadurch nicht neu implementiert.

Organization-Sync verwendet entweder die gewählte App-Verbindung oder die erste gültige Discovery-Verbindung. Channels bleiben jeweils dem tatsächlichen Account zugeordnet. Organization-Publishing-Rechte werden pro Verbindung ausgewertet; der bisherige Live-Testpost-Endpunkt bleibt auf persönliche Profile begrenzt. Ein neuer Organization-Publishing-Dialog ist nicht Teil dieser Änderung. Events haben einen Resolver und Matrixeinträge für `r_events`/`rw_events`; eine bislang nicht vorhandene Event-API/-Oberfläche wurde nicht erfunden.

## Sicherheit

Credential- und Tokenverschlüsselung verwendet das bestehende Kryptomodul. Keine Klartextmigration, keine Secrets in GET-Antworten und keine Tokens, Codes, States oder Session-IDs in den neuen Logs. Namen und Scope-Listen werden als DOM-Text dargestellt. Cross-App-Zugriffe, falsche Providerzuordnungen, fehlende Adminrechte/CSRF sowie Löschen referenzierter Apps werden abgewehrt. Die Tests nutzen synthetische Daten, strikte Mock-App-Credentials und eine isolierte Testdatenbank.

## Dateien

Neu:

- `api/provider-apps.php`
- `database/migrations/012-provider-apps.sql`
- `public/js/core/providerApps.js`
- `public/tests/provider-apps-tests.js`
- `tests/migration-provider-apps.php`
- `tests/provider-apps-http.py`
- `tests/provider-apps-tokens.php`
- `docs/provider-apps.md`

Geändert:

- `api/bootstrap.php`, `api/index.php`
- `api/linkedin-oauth.php`, `api/linkedin-oauth-result.php`, `api/linkedin-channels.php`, `api/linkedin-posts.php`
- `api/campaign/repository.php`, `api/campaign/routes.php`, `api/campaign/service.php`
- `public/js/app.js`, `public/js/core/api.js`, `public/js/core/linkedinOAuthResult.js`, `public/js/campaign/ui.js`
- `public/css/app.css`, `public/tests/test.js`
- `scripts/migrate.php`
- `tests/run-all.sh`, `tests/campaign-unit.php`, `tests/mock-linkedin-router.php`

## Tests und Entscheidung

**Vollständige Suite: 452 PASS / 0 FAIL, einschließlich Browser: 77 PASS / 0 FAIL.** `git diff --check`: PASS. Geprüft werden Migration und Wiederholung, unveränderte verschlüsselte Daten, CRUD, parallele App-States, Cross-App-Schutz, getrennte Grants, Token A für Posting, Token B für Lesen/Kommentare, App-spezifische Fehler, Disconnect-Isolation, Formularverhalten und schmale Darstellung. Die bestehende vollständige Regression bleibt Bestandteil des Laufs.

**Entscheidung: JA.** Mehrere getrennte Developer-Apps werden verwaltet und die vorhandenen Funktionen können passende Verbindungen verwenden. Event- und Organization-Publishing-Oberflächen sowie ein allgemeiner Netzwerkfeed bleiben die oben beschriebenen, bereits bestehenden Funktionsgrenzen.

Kein Commit, kein Deployment, keine produktiven LinkedIn-Aufrufe und keine produktive Datenbankänderung.
