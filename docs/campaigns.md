# Kampagnenmodul – Umsetzung und Abschlussbericht

Stand: 5. September 2026. Kein Deployment, kein Commit, keine produktiven Social-Media-Aufrufe. Vorhandene Änderungen am Plattformfassungs-Overlay wurden erhalten.

## Kampagne / relevante Beiträge / Auswahl

Der eigenständige, anmeldepflichtige Hauptreiter **Kampagne** enthält Kampagnenübersicht, relevante Beiträge, Auswahl und Warnungen, Basisantwort, konkrete Antworten sowie Status und Abschlussfreigabe. Editor, Beiträge und Admin bleiben bestehen.

V1 verwendet eine persistente Sammlung manuell hinzugefügter Beiträge. Jeder Treffer zeigt Plattform, Autor, verfügbares Datum, Auszug, Original-Link, Relevanz, Auswahl und Status. Filter: Plattform, Status, Relevanz, Suchwort. Keine erfundenen Treffer, keine KI-Bewertung und kein Scraping. Die Suche filtert vorhandene Auszüge und Autoren; sie ist noch keine Relevanzbewertung.

Ein Beitrag kann ohne verbundenes Konto allein mit Plattform und URL erfasst werden. Autor und kurze Notiz sind optional. Eine explizit vom Provider gelieferte Share-/UGC-Post-URN und ein bestehendes Konto können optional zugeordnet werden. Aus URLs werden **keine URNs abgeleitet**. Manuelle Einträge erhalten intern einen als `manual:` gekennzeichneten Bezeichner; dieser ist keine Provider-ID und wird niemals an LinkedIn versendet.

`relevance_score` (nullable, 0–100), `relevance_reason` und `relevance_source` bilden die providerneutrale Erweiterungsstelle für Keywords, Kontakte, Favoriten, Branchen, Unternehmen, BNI, Themen, manuelle Markierungen und spätere KI-Auswertung. V1 setzt bei manueller Erfassung `manual` und „Manuell hinzugefügt“; nicht bewertete Beiträge erhalten keinen erfundenen Score.

Mehrfachauswahl erzeugt jeweils eine Antwortinstanz, niemals eine Veröffentlichung. SocialDeck warnt standardmäßig ab 5 und verstärkt ab 10 ausgewählten Zielen. Die Variablen `CAMPAIGN_WARNING_TARGETS` und `CAMPAIGN_STRONG_WARNING_TARGETS` werden zentral serverseitig ausgewertet und an die Oberfläche geliefert. Es handelt sich um UX-Warnwerte, nicht um LinkedIn- oder andere Providerlimits. Die Anzahl allein blockiert keine Veröffentlichung.

## Basisantwort / konkrete Antworten / Plattformen

Basisänderungen aktualisieren nicht individualisierte Antworten. Individuelle Antworten bleiben erhalten. „Aus Basisantwort aktualisieren“ ersetzt gezielt eine Antwort; bei Custom-Text fragt die Oberfläche vor dem Überschreiben nach. Bereits veröffentlichte Antworten bleiben als Nachweis unverändert.

Die aktiven Textbausteine und die bestehenden Cursor-/Markierungshelfer werden wiederverwendet. `resolveTextWithBlocks()` kapselt die vorhandene rekursive Auflösung und wird vom normalen Editor sowie von der Kampagnenfreigabe verwendet. Keine zweite Placeholder-Engine.

Jedes Ziel und jede Plattform kann deaktiviert werden. Die Daten und Texte bleiben gespeichert. Deaktivierte Ziele werden von der Freigabe und vom Versand ausgeschlossen. Die Antwortkarten enthalten den Originalbeitrag, Status, Veröffentlichbarkeit, Kopierfunktion und Vorschau.

Das vergrößerbare Overlay verwendet den vorhandenen Overlay-Controller: dieselbe Karte und dieselbe Textarea werden in ein modales Dialogfenster verschoben. Kein zweiter Editor-State. Desktop: Original und Antwort nebeneinander; kleine Bildschirme: untereinander. Escape, Schließen und Fokuswiederherstellung werden vom bestehenden Controller übernommen.

## LinkedIn: Dokumentation, Scopes und tatsächliche Funktionen

Geprüfte offizielle Dokumentation:

- [Comments API, Version 2026-08](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/comments-api?view=li-lms-2026-08)
- [Posts API, Version 2026-08](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-08)

Die Comments API nennt `w_member_social_feed` für Kommentare im Namen eines Mitglieds. `r_member_social_feed` ist eingeschränkt und nur ausgewählten Entwicklern zugänglich. Konfigurierte oder angeforderte Scopes werden nicht als erteilte Berechtigungen behandelt. Verwendet werden die im bestehenden OAuth-Ablauf aus der Token-Antwort gespeicherten Grants.

**Wichtige Grenze:** Ein Read-Scope stellt keinen allgemeinen Feed der Kontakte bereit. V1 implementiert keine allgemeine Netzwerk-Discovery. Bekannte LinkedIn-Posts können einzeln über die offizielle Posts API aktualisiert werden. Weil deren Dokumentation für Member-Posts zusätzlich `r_member_social` nennt, prüft dieser Adapter konservativ beide Leseberechtigungen: `r_member_social_feed` und `r_member_social`. Ohne sie bleibt die manuelle Sammlung vollständig nutzbar. Der Scope allein garantiert außerdem nicht die Zugänglichkeit eines fremden Beitrags; 401/403/429 werden verständlich abgefangen.

Direkte Mitgliedskommentare verwenden `POST /rest/socialActions/{encoded Share-/UGC-Post-URN}/comments` mit `actor`, `object` und dem geprüften Antworttext in `message.text`. API-Version und Sicherheitsheader stammen aus der bestehenden LinkedIn-Infrastruktur.

Vor jedem Versand: Provider aktiviert, Konto verbunden, bekannte noch gültige Token-Laufzeit, gespeichertes Token, tatsächlich gewährter Schreibscope, valide externe Post-URN, nicht leere Antwort, aktives Ziel/aktive Plattform und aktuelle Abschlussfreigabe. Unbekannte Token-Laufzeit wird nicht als gültig angenommen. Instagram und Facebook haben in V1 keinen Feed-/Kommentaradapter; für sie gilt der manuelle Fallback.

Die Admin-Konten zeigen Kampagnen-Lese- und Schreibmöglichkeiten auf Grundlage gespeicherter Grants und Ablaufdaten. Reale Kontoberechtigungen wurden für diesen Auftrag nicht per Live-Aufruf überprüft. Die Oberfläche prüft sie zur Laufzeit.

## Veröffentlichung / Teilfehler / Wiederholungen

1. Kampagne in der DB speichern; konkurrierende Änderungen werden durch eine Revisionsnummer erkannt.
2. Server erstellt eine Abschlussübersicht mit Namen, Plattformzahlen, aktiven/deaktivierten Zielen und jeder aufgelösten Antwort. Bei mehreren beziehungsweise gleichen normalisierten Antworten erscheinen zusätzliche Warnungen.
3. Die endgültigen Texte werden als unveränderlicher Freigabe-Snapshot in der DB gespeichert. Textbausteinänderungen nach diesem Schritt ändern den freigegebenen Text nicht.
4. Nutzer bestätigt ausdrücklich die geprüften Antworten und startet den Versand. Der zufällige Freigabetoken ist zehn Minuten gültig, wird nur gehasht gespeichert und vor dem ersten Versand einmalig verbraucht. Jede Kampagnenänderung entwertet ihn.
5. Ein Datenbanklock verhindert konkurrierende Schreib-/Versandoperationen auf derselben Kampagne. Provideradapter arbeiten sequentiell. Keine Queue, unsichtbare Hintergrundkampagne, automatische Wiederholung, Request-Flut oder zufällige Verzögerung.
6. Ergebnisse werden sofort je Ziel gespeichert. Erfolge bleiben bei späteren Fehlern erhalten; die Kampagne wird gegebenenfalls `partially_published`.

Fehlgeschlagene Ziele werden bei einer normalen neuen Freigabe ausgelassen. „Erneut versuchen“ erstellt eine neue Freigabe nur für das betreffende fehlgeschlagene Ziel. Bei unklarer Zustellung, beispielsweise Verbindungsabbruch oder Serverfehler, wird kein erneuter API-Versand angeboten: zuerst Original prüfen. Ein Prozessabbruch nach Beginn eines Versands hinterlässt `publishing` als sichtbaren ungeklärten Zustand. Keine automatische Wiederaufnahme.

Ohne API-Veröffentlichbarkeit bleiben „Kommentar kopieren“ und „Originalbeitrag öffnen“ nutzbar. Manuelle Veröffentlichung wird nicht vorgetäuscht: Erst eine gesonderte ausdrückliche Bestätigung des Nutzers setzt das Ziel auf veröffentlicht. Bereits im Netzwerk veröffentlichte Kommentare werden beim Löschen einer Kampagne nicht entfernt.

## Datenmodell / Migration / Sicherheit

Neue Migration: `database/migrations/011-campaigns.sql`, außerdem im Schema für Neuinstallationen enthalten.

- `campaigns`: Name, Basistext, Status, Eigentümer, Plattformschalter, Revision, befristete Freigabe und Zeitstempel.
- `campaign_targets`: Verbindung zum Engagement-Eintrag, vorhandenen Provider-/Kontobezeichnern, konkrete Antwort, Custom-Flag, Aktivierung, Status und Veröffentlichungs-/Fehlerdaten.
- `engagement_items`: minimierter Fremdbeitragsauszug, externe Bezeichner/URL, optionales Konto, Relevanz, Status und Zeitstempel. Unique-Constraint über Eigentümer, Provider und SHA-256 des Kontos samt expliziter URN beziehungsweise manueller URL; zusätzliche Unique-Constraint verhindert doppelte Ziele innerhalb einer Kampagne.

Manuelle Einträge erlauben ein NULL-Konto, damit Tests und Vorbereitung ohne OAuth-Verbindung funktionieren. Bestehende `posts` und `post_targets` bleiben getrennt. Die gemeinsame Providerdefinition, Konten, OAuth, Verschlüsselung und LinkedIn-Header werden wiederverwendet. Keine Tokens im Browser, keine externen Rohantworten oder Secrets in Kampagnenlogs. Fremdinhalte werden als Text dargestellt; Links erlauben nur HTTPS. Alle Kampagnen-/Engagement-Endpunkte verlangen Auth, Writes zusätzlich CSRF; Kampagnen und Einträge sind nach `created_by` isoliert.

`scripts/migrate.php` berücksichtigt nun Migration 011 sowie die zuvor dort fehlende Medienmigration 010. Das Migrationsskript bleibt idempotent. Migrationen wurden nur in der isolierten Testdatenbank ausgeführt, nicht in der laufenden lokalen oder einer produktiven Datenbank. Für eine bestehende Installation ist vor Nutzung des neuen Reiters die Migration erforderlich; kein Deployment wurde durchgeführt.

## Übertragbarkeit als Skeleton-Modul

- Domainregeln: `api/campaign/core.php`, `public/js/campaign/core.js`.
- Providervertrag/LinkedIn-Adapter: `api/campaign/providers.php`.
- Persistenz und Anwendung: `api/campaign/repository.php`, `service.php`.
- HTTP-Grenze: `api/campaign/routes.php`, `public/js/campaign/api.js`.
- UI: `public/js/campaign/ui.js`.
- Navigation und Host-Anbindung: `public/js/app.js`, `public/index.html`.

Der Domain-Core kennt weder LinkedIn-Markup noch SocialDeck-Seitenstruktur. Für einen Transfer müssen DB/Auth/CSRF, Provider-/Accountzugriff, Textbausteine und Hostnavigation angebunden werden. Es wurden keine Skeleton-Dateien verändert. Das ist eine modular getrennte Implementierung, noch kein separat paketiertes Skeleton-Plugin.

## Dateien

Neu: `api/campaign/{core,providers,repository,service,routes}.php`, `public/js/campaign/{core,api,ui}.js`, `database/migrations/011-campaigns.sql`, `tests/campaign-unit.php`, `tests/campaign-http.py`, `public/tests/campaign-tests.js`, dieser Bericht.

Geändert: `README.md`, `api/bootstrap.php`, `api/index.php`, `api/text-blocks.php`, `public/js/core/api.js`, `public/js/app.js`, `public/index.html`, `public/css/app.css`, `database/schema.sql`, `scripts/migrate.php`, `.env.example`, `docker-compose.yml`, `docker-compose.prod.yml`, `tests/run-all.sh`, `tests/mock-linkedin-router.php`, `public/tests/test.js`.

Der gemeinsame JSON-Parser akzeptiert nun korrekt leere JSON-Objekte `{}` für Aktionen ohne Nutzdaten; Arrays bleiben ungültig. Die bestehenden Änderungen an `public/js/core/variantOverlay.js`, App-Markup/CSS und Tests wurden erhalten.

## Tests und Entscheidung

Testbefehl: `bash tests/run-all.sh`. Vollständige Docker-Suite mit isolierter Datenbank und lokalem LinkedIn-Mock; Browserprüfungen über Chromium. Geprüft werden CRUD über HTTP und DB, Auth/CSRF, Eigentümerisolation, Ein-/Mehrfachauswahl, Deaktivierung, Custom-Schutz, Bausteine und Freigabe-Snapshot, Warnungen, Deduplizierung, leere Sammlung, bekannte API-Posts, fehlende Scopes, 401/403/429, Mock-Kommentare, Teilfehler, verbrauchte/entwertete Freigaben, expliziter Retry, Overlay-State und XSS. Die vorhandene Editor-/Medien-/Textbaustein-/OAuth-Suite läuft mit.

Abschließendes Ergebnis: **401 PASS, 0 FAIL**, davon **66 Browserprüfungen PASS, 0 FAIL**. `git diff --check` ist ebenfalls fehlerfrei. Es werden keine Live-Fähigkeiten aus Mock-Erfolgen abgeleitet.

**Entscheidung: JA**, nach Einspielen der Migration ist SocialDeck technisch und UI-seitig auf Kampagnen aus einem oder mehreren Beiträgen mit individuellen Antworten, Ziel-/Plattformdeaktivierung und kontrollierter Veröffentlichung vorbereitet. Direkter API-Versand bleibt an reale Providerberechtigungen und gültige Post-IDs gebunden. Automatische allgemeine Feed-Discovery ist in V1 nicht vorhanden; der manuelle Fallback und die eingeschränkte Aktualisierung bekannter LinkedIn-Posts bilden die tatsächliche Datenquelle.
