# Kampagne: Layoutangleichung

Nur UI-Markup, Darstellung und UI-Tests wurden geändert. Campaign-Core, API, Datenmodell, Migrationen, Scopes, Versand, Freigabeprüfung und Retry-Regeln bleiben unverändert.

## Vorherige Abweichungen

- Eigene Radien (12/16 px), Rahmenfarben und Overlay-Abmessungen statt der vorhandenen Panel-/Modal-Muster.
- Hauptkarten ohne die üblichen Innenabstände; zusätzliche Auswahlkarte und große, einzeln angeordnete Aktionsbuttons.
- Feed als mehrspaltiges Kachelraster mit eigenem Scrollbereich statt scanbarer Beitragsliste.
- Ungestaltete Selects, Textfelder mit vom umschließenden Label geerbter Fettschrift.
- Admin-Placeholder-Pills statt der Textbausteinkarten des normalen Editors.
- Status überwiegend als Fließtext; Plattformschalter ohne Editor-Providerdarstellung.
- Eigenes Warnfarbschema; Antwort-Overlay ohne die gemeinsame Dialogkopfzeile.

## Angleichung

Die Hauptansicht verwendet dieselbe `.app-shell`-Breite und `.standalone-view` wie Beiträge. Die Inhaltsblöcke übernehmen den 20-px-Abstand des Editors. Überschriften: Kampagnenübersicht, Relevante Beiträge, Kampagnenantwort, Konkrete Antworten, Veröffentlichung.

Kampagnenübersicht und Treffer verwenden Historien-/Beitragslistenmuster. Filter sind horizontal, umbrechend und gleich hoch. Auswahl und Warnhinweise befinden sich im Feedbereich, ohne zusätzliche Panel-Verschachtelung. Auszüge sind in der Trefferliste auf drei Zeilen begrenzt; die Antwortkarte zeigt den vorhandenen Auszug vollständig.

Antwortkarten verwenden Plattformfassungs-Panels mit Aktivierung, Provider/Autor, Status und Vergrößern-Icon im Kopf. Original und Antwort stehen auf Desktop nebeneinander; die Antwort erhält mehr Breite. Aktionen sind in vorhandenen Buttonreihen zusammengefasst. Speichern und Löschen stehen gemeinsam vor der primären Abschlussübersicht; die endgültige Freigabe bleibt eine ausdrückliche Aktion im Review-Dialog.

Textbausteinkarten werden über die vorhandene Textbaustein-Komponente mit gemeinsamer `post`-Darstellung sowohl im Editor als auch in Kampagnen erzeugt. Einfügen, Cursorposition, Auswahlersetzung und Auflösung sind unverändert.

Status/Anzahl verwenden vorhandene Badges. Informationen verwenden `field-hint`, Warnungen die bestehende Statuspalette, Fehler `form-error`. Keine neue Farbpalette oder Buttonvariante.

## Overlay und Responsive

Der vorhandene `createVariantOverlayController` bleibt unverändert. Die Kampagne verwendet zusätzlich dieselben `platform-variant-dialog`-, Shell-, Header- und Content-Klassen mit Verkleinern-/Schließen-Icons. Native Dialog-Modalität, Escape, Hintergrundscrollsperre und Fokuswiederherstellung bleiben erhalten. Review und manuelle Erfassung nutzen `publish-confirmation`.

Desktop: volle Hauptbreite, horizontale Filter, Treffer untereinander, großzügiger Antworteditor. Tablet: Filter/Listen umbrechend. Mobil: einspaltige Antworten, umbrechende Aktionen, vollflächiges Antwort-Overlay entsprechend dem bestehenden 760-px-Breakpoint. Layoutprüfungen erfolgen bei 1280, 900, 390 und 320 px; geprüft werden Seiten-/Dialogoverflow sowie State, Fokus, Escape und Plattformschalter.

## CSS-Wiederverwendung

`app-shell`, `standalone-view`, `post-editor`, `panel`, `editor-panel`, `panel-heading compact`, `history-item`, `posts-list`, `post-list-item`, `provider-list`, `provider-option`, `platform-variant`, `variant-head-actions`, `icon-button`, `button-row`, `button-primary`, `button-secondary`, `button-quiet`, `status-badge`, `count-badge`, `publish-bar`, `publish-confirmation`, `post-text-blocks`, `post-text-block-list`, `post-text-block` und die `platform-variant-dialog`-Klassen.

Kampagnenspezifische Klassen beschreiben nur die eigene Struktur: Shell, Feed/Filter/Metadaten, Auswahl, Plattformen, Original/Antwort, Warnung und die drei Dialogkontexte. Der frühere separate Kampagnen-CSS-Block wurde ersetzt, nicht durch weitere unabhängige Karten-/Modalstile ergänzt. Neue Strukturklassen: `campaign-access`, `campaign-feed-meta`; bestehende Kampagnenklassen wurden angepasst.

## Dateien

- `public/js/campaign/ui.js`: Markup, Klassen, Überschriften und Aktionsgruppierung.
- `public/css/app.css`: Wiederverwendung und responsive Struktur.
- `public/index.html`: gemeinsame Hauptansichtsklasse.
- `public/js/core/textBlocks.js`, `public/js/app.js`: gemeinsame Darstellung der Editor-Textbausteinkarten.
- `public/tests/campaign-tests.js`, `public/tests/test.js`: bestehende Tests angepasst und Layouttests eingebunden.
- `public/tests/campaign-layout.html`, `campaign-layout-fixture.js`, `campaign-layout-tests.js`: synthetische Fixture und echte Browserlayoutprüfungen. Die Tests verwenden `srcdoc`, um die bestehende `frame-ancestors 'none'`-Sicherheitsrichtlinie nicht ändern zu müssen. Keine echten Konten oder Posts.
- `docs/campaign-layout.md`: dieser Bericht.

## Ergebnis

Vollständige Suite: **405 PASS, 0 FAIL**, davon **70 Browserprüfungen PASS, 0 FAIL**. Nach den letzten CSS-Feinheiten wurden zusätzlich alle vier gezielten Layoutprüfungen erneut erfolgreich ausgeführt. Keine Browserfehler in den Layout-Fixtures. Die vollständige bestehende Suite läuft in der isolierten Testumgebung mit lokalen API-Mocks; keine Änderung einer bestehenden Anwendungsdatenbank.

Entscheidung: **JA** – Kampagne verwendet nun das vorhandene visuelle und strukturelle System von SocialDeck.

Kein Deployment, kein Commit, keine Änderung produktiver/laufender Datenbanken und keine echten Social-Media-Aufrufe.
