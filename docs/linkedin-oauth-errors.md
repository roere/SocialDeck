# LinkedIn-OAuth: Scope-Snapshots und Fehlerdarstellung

## OAuth-Request

Quelle bleibt die gespeicherte LinkedIn-Providerkonfiguration. Das Admin-Feld heißt nun „Konfigurierte Scopes“. Die vorhandene Trennung nach Leerzeichen/Kommas und Deduplizierung bleibt bestehen. SocialDeck ergänzt keine Basis- oder Zusatz-Scopes und versucht nicht, einen abgelehnten Scope-Satz durch stilles Entfernen einzelner Namen zu reparieren. Die bestehende Voraussetzung `openid`/`profile` wird weiterhin explizit validiert, nicht automatisch ergänzt.

## Requested Scopes und Fehler-UX

Jeder neue serverseitige OAuth-State enthält `created_at`, `requestedScopes` und die Information, ob bereits ein lokaler Token vorhanden war. Der Schlüssel bleibt der SHA-256-Hash des zufälligen State-Wertes. Snapshot und Request werden aus derselben geladenen Konfiguration erzeugt. Beim Callback wird genau dieser Snapshot einmalig konsumiert. Parallele Requests behalten getrennte Snapshots. Ältere State-Formate funktionieren weiterhin; bei ihnen wird keine Scope-Liste aus der aktuellen Konfiguration erfunden.

Ein sicheres Ergebnisobjekt wird in der angemeldeten Sitzung gespeichert und über den vorhandenen Admin-Providerendpunkt bereitgestellt. Das Ergebnis ist eine Stunde sichtbar, wird durch das nächste Callback-Ergebnis ersetzt und endet spätestens mit der Sitzung. Es ist kein dauerhaftes Auditarchiv. Browserparameter liefern weder die angefragte Scope-Liste noch eine Diagnose.

- `invalid_scope` / `unauthorized_scope_error`: vollständige angefragte Scope-Liste, Hinweis auf mindestens eine möglicherweise nicht verfügbare Berechtigung und auf Products/Scopes im Developer Portal. Keine einzelne Berechtigung wird als Ursache behauptet.
- `access_denied`, `user_cancelled_login`, `user_cancelled_authorize`: Abbruchmeldung ohne Behauptung ungültiger Scopes. Die angefragte Liste bleibt sichtbar.
- Ungültiger/abgelaufener State: eigene Sitzungsmeldung; ohne gültigen Snapshot wird die Liste ausdrücklich als nicht verfügbar bezeichnet.
- Tokenaustausch: eigene Kategorie mit den bisherigen Unterscheidungen für Client, Code, Redirect URI, Rate Limit, DNS, TLS, Timeout, Transport und ungültige Antwort. Nur ein expliziter Scope-Fehlercode der Tokenantwort wird als Ablehnung des Scope-Satzes behandelt.
- Profilabruf: eigene Userinfo-Meldung; keine Scope-Vermutung.
- Sonstige Authorization-Fehler: generische Autorisierungsmeldung und vorsichtig formulierter Prüfhinweis; keine Einzelzuordnung.

`error_description` wird nicht angezeigt und nicht für die Klassifizierung von Authorization-Fehlern verwendet. Auch eine vermeintlich eindeutige Einzel-Scope-Angabe wird in dieser Umsetzung nicht übernommen. Das vermeidet unbelegte Diagnosen und die Ausgabe fremder Rohdaten. Die bestehende technische Redirect-Mismatch-Erkennung beim Tokenaustausch bleibt erhalten.

Der LinkedIn-Block öffnet sich nach dem OAuth-Redirect. Seine Fehlermeldung enthält den sicheren allgemeinen Link `https://www.linkedin.com/developers/apps`; es wird kein app-spezifischer Link konstruiert. Konfiguration speichern behält die bestehende Offenhalten-/Erfolgsmeldungslogik.

## Grants, Tokenstatus, Kampagne und Organization

Bei Erfolg werden ausschließlich die Scopes aus der Tokenantwort in `social_accounts.scopes` gespeichert. Die Providerkonfiguration bleibt davon getrennt. Ein Request mit fünf konfigurierten Scopes und einer Antwort mit drei bestätigten Scopes aktiviert nur die tatsächlich bestätigten Funktionen.

Bei Fehlern werden vorhandene Accountdaten, Tokens und Channels nicht gelöscht oder ersetzt. Für einen Versuch mit vorhandenem Token zeigt der Adminblock „Vorhandene Verbindung – Gültigkeit nach Scope-Änderung noch nicht bestätigt“ und den Hinweis auf gegebenenfalls notwendige erneute Autorisierung. Es gibt keine Garantie, dass LinkedIn den bisherigen Token weiterhin akzeptiert, und keinen neuen Introspection- oder Live-Prüfaufruf.

Die vorhandenen Kampagnenadapter prüfen weiterhin ausschließlich gespeicherte Grants und Tokenlaufzeiten. Konfigurierte Feed-/Kommentarrechte aktivieren keine Funktionen. Dasselbe gilt für Organization-Discovery/-Publishing. Fehlende Rechte lassen den manuellen Kampagnenmodus bestehen. Diese Provider-/Publishing-Regeln wurden nicht geändert.

## Logging und Sicherheit

Logs enthalten Phase, gefilterte Scope-Namen, feste Fehlercodes/-kategorien, HTTP-Status und technische Fehlerklassen. Rohe Providerbeschreibungen, Transportwarnungen, Token-Endpunkt/Redirect-URLs sowie Session-/State-Hashes wurden aus der OAuth-Diagnose entfernt. Keine Tokens, Codes, Secrets, State-Klartexte oder Session-IDs. Auch das Nginx-Access-Log verwendet nun ein Format ohne Queryparameter oder Referer, damit Callback-Codes und States dort nicht auftauchen. Die UI erzeugt DOM-Text statt fremdes HTML. Auth, CSRF, State-TTL und Einmalverbrauch bleiben bestehen.

## Dateien und Migration

Neu:
- `api/linkedin-oauth-result.php`
- `public/js/core/linkedinOAuthResult.js`
- `public/tests/linkedin-oauth-result-tests.js`
- `tests/oauth-result-http.py`
- `docs/linkedin-oauth-errors.md`

Geändert:
- `api/linkedin-oauth.php`, `api/index.php`, `nginx/default.conf`
- `public/js/app.js`, `public/css/app.css`, `public/tests/test.js`
- `tests/oauth-state-unit.php`, `tests/token-exchange-unit.php`, `tests/run-all.sh`

Keine Migration und keine neue Datenbanktabelle erforderlich. Keine Änderungen an Kampagnen-Core oder Datenmodell.

## Tests und Entscheidung

Vollständige Suite: **423 PASS, 0 FAIL**, einschließlich **73 Browserprüfungen PASS, 0 FAIL**. HTTP-Tests prüfen Request A → Konfigurationsänderung B → Callback mit Snapshot A, unveränderte lokale Accountdaten nach Ablehnung, Fehlerkategorien, fünf konfigurierte gegenüber drei gewährten Scopes und Capability-Auswertung. Unit-/Browsertests prüfen parallele/ältere States, Einmalverbrauch, Abbruch ohne Scope-Diagnose, Secret-freies Logging, sichere Darstellung und Tokenstatus-Hinweise. Bestehende Posting-, Medien-, Channels-, Kampagnen- und OAuth-Regressionen sind grün.

**Entscheidung: JA.** SocialDeck kann den Scope-Satz des konkreten fehlgeschlagenen Requests darstellen, ohne einen einzelnen Scope fälschlich als Ursache zu benennen.

Kein Deployment, kein Commit und keine produktiven LinkedIn-Aufrufe. Tests laufen ausschließlich mit lokaler Mock-API und isolierter Testdatenbank.

Referenz zur Token-Lifecycle-Einschränkung: [Offizieller LinkedIn Authorization Code Flow](https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow), Abschnitt „Access Token Scopes and Lifetime“.
