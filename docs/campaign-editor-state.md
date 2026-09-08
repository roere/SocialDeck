# Kampagnenübersicht und Bearbeitung

## Standardansicht

Der Reiter Kampagne zeigt zunächst ausschließlich die Kampagnenübersicht mit vorhandenen Kampagnen, Kennzahlen und den Aktionen „Neue Kampagne“ und „Kampagne laden“. Beitragspool, Antworten und Veröffentlichung sind im geschlossenen Zustand nicht gerendert. Der Editorzustand wird ausdrücklich durch `campaignEditorMode` (`null`, `create`, `edit`) und die aktive lokale Kampagne gesteuert, nicht aus DOM-Sichtbarkeit abgeleitet.

## Öffnen und Bearbeiten

„Neue Kampagne“ initialisiert ausschließlich einen lokalen Entwurf und öffnet den bestehenden vollständigen Editor. Es entsteht dadurch kein DB-Datensatz. „Kampagne laden“ liest den gespeicherten Stand und verwendet denselben Editor. Name, Antworten, Ziele, Plattformschalter und Veröffentlichungsstatus werden unverändert aus dem vorhandenen Datenmodell übernommen. Über dem Bearbeitungsbereich steht „Aktuelle Kampagne“ mit dem aktuellen Namen. Die Übersicht bleibt darüber sichtbar.

## Speichern

Der primäre Button „Kampagne speichern“ nutzt die vorhandene Speicherung, aktualisiert die Übersicht und schließt anschließend den Editor. Aktive Kampagne, Modus und Dirty-State werden zurückgesetzt; die Erfolgsmeldung lautet „Kampagne gespeichert.“ Bei einem Fehler bleibt der Editor mit seinen Eingaben erhalten. Schlägt nur die Aktualisierung der Übersicht nach einem erfolgreichen Speichern fehl, bleibt die bereits erhaltene Kampagnen-ID lokal bestehen, damit eine Wiederholung keinen zweiten Datensatz anlegt.

Die interne Speicherung vor Abschlussprüfung, gezielter Wiederholung oder manueller Veröffentlichungsbestätigung hält den Editor offen. Die vorhandene Freigabe- und Publishing-Logik bleibt dadurch nutzbar; nur der ausdrücklich betätigte Speicherbutton kehrt zur Übersicht zurück.

## Abbrechen und Dirty-State

„Abbrechen“ schließt einen unveränderten Editor direkt. Änderungen an Name, Basisantwort, Zielauswahl, individueller Antwort, Zielaktivierung und Plattformaktivierung setzen den vorhandenen Dirty-State. Filter des gemeinsamen Beitragspools sind keine Kampagnenänderungen.

Bei lokalen Änderungen zeigt ein Dialog im bestehenden SocialDeck-Design:

- „Ungespeicherte Änderungen verwerfen?“
- „Die Änderungen an dieser Kampagne wurden noch nicht gespeichert.“
- „Weiter bearbeiten“ und „Änderungen verwerfen“

„Weiter bearbeiten“ sowie Escape erhalten die Eingaben. Nur bestätigtes Verwerfen schließt den Editor. Abbrechen ruft keine Speicher- oder Lösch-API auf. Bei erneutem Laden einer bestehenden Kampagne wird deren letzter gespeicherter Stand gelesen. Dialoge und Antwort-Overlay werden beim Schließen des Editors mit geschlossen.

## Navigation und Kampagnenwechsel

Neue Kampagne und Laden einer anderen Kampagne verwenden denselben Verwerf-Dialog. Eine fehlgeschlagene Ladeanfrage ersetzt den aktuellen Entwurf nicht. Der Hauptrouter fragt vor dem Verlassen der Kampagne `beforeLeave()` ab. Bei Ablehnung bleibt der Kampagnenreiter sichtbar und seine URL wird wiederhergestellt. Bei Zustimmung oder unverändertem Editor wird der lokale Bearbeitungszustand geschlossen. Während einer laufenden Kampagnenaktion wird ein Reiterwechsel zurückgehalten, damit verspätete Antworten keinen bereits verlassenen Editor verändern.

## Layout und Dateien

Die angeglichene Seitenbreite, Providerkarten, kompakten Listen, Textbausteine und Antwort-Overlays bleiben bestehen. Am unteren Ende stehen „Kampagne speichern“ als Primäraktion, „Abbrechen“ und „Abschlussübersicht prüfen“. Im geschlossenen Zustand bleibt nur die Übersicht.

Geändert:

- `public/js/campaign/ui.js`: Editorzustand, Verwerf-Dialog, Speicherabschluss, gemeinsame Verlassen-Prüfung.
- `public/js/app.js`: Navigationsschutz vor dem Ansichtswechsel.
- `public/css/app.css`: Editorcontainer und Kennzeichnung der aktuellen Kampagne.
- `public/tests/campaign-tests.js`: bestehender Workflowtest startet ausdrücklich mit „Neue Kampagne“.
- `public/tests/campaign-layout-tests.js`: zusätzliche Kennzeichnung im bestehenden Layouttest.
- `public/tests/test.js`: Registrierung der neuen Browsertests.

Neu:

- `public/tests/campaign-editor-tests.js`: Verhaltenstests mit ausschließlich lokal simulierten API-Antworten.
- `docs/campaign-editor-state.md`: dieser Bericht.

Keine Änderung an Backend, APIs, Datenmodell oder Migrationen. Keine Änderung der fachlichen Kampagnenlogik.

## Tests und Entscheidung

**Vollständige Suite: 459 PASS / 0 FAIL, einschließlich Browser: 84 PASS / 0 FAIL.** `git diff --check`: PASS. Neue Browserfälle prüfen Initialansicht, Create, Laden, Save, Fehlererhalt, Cancel ohne Schreibzugriff, Verwerfen/Weiterbearbeiten, Kampagnenwechsel, Navigationsschutz und interne Speicherung für die Abschlussprüfung. Die bisherigen Layout-/Overlaytests bei 1280, 900, 390 und 320 Pixeln bleiben Teil der Suite.

Entscheidung: JA. Standardmäßig ist ausschließlich die Übersicht sichtbar. Der vollständige Editor wird über „Neue Kampagne“ oder „Kampagne laden“ geöffnet und über erfolgreiches Speichern oder bestätigtes Abbrechen geschlossen.

Kein Commit, kein Deployment, keine produktive Datenbankänderung und keine Social-Media-Aufrufe. Die vollständige Regression verwendet ausschließlich die isolierte Testdatenbank und lokale Provider-Mocks.
