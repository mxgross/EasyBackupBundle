# Priorisierte Verbesserungen — EasyBackupBundle

Reihenfolge: absteigend nach Priorität (1 = kritisch). Kurzbeschreibung und vorgeschlagene Maßnahme.

1) Kritisch: Vermeidung von Command Injection & sichere Prozess-Ausführung
   - Problem: DB-Dump-Kommando wird aus Templates zusammengesetzt; `execute()` ruft `proc_open(escapeshellcmd($cmd), ...)` auf. `escapeshellcmd` ist nicht ausreichend, und Zusammensetzung aus externen Werten bleibt riskant.
   - Maßnahme: Verwenden von Prozess-API (Symfony Process Component) mit Argument-Array, oder sichere Konstruktion/Validierung der Parameter; niemals rohen, zusammengesetzten String mit `escapeshellcmd` ausführen.

2) Kritisch: Direkte Nutzung von `$_SERVER['DATABASE_URL']` und fehlende Validierung
   - Problem: Konstruktoren lesen `$_SERVER['DATABASE_URL']` direkt; kein Fallback/Validierung.
   - Maßnahme: Konfigurierbare, injizierbare DB-URL (über DI), Validierung/Parsing mit robustem Parser; Fehlerfälle sauber behandeln (Exceptions, verständliche Fehlermeldungen).

3) Hoch: Restore-Mechanik überschreibt Dateien ohne ausreichende Sicherheitschecks
   - Problem: Restore kopiert Dateien zurück in die Produktionspfade; nur eine sehr kleine Blacklist existiert.
   - Maßnahme: Implementieren eines Dry-Run / Preview-Modus (bereits teilweise vorhanden), erweiterte Whitelist/Mapping, Owner/Permission-Checks, Bestätigungsschritt, und ggf. Sandbox/Temp-Pfad mit klarer Benutzer-UI.

4) Hoch: Unsichere Pfad-Manipulation / potenzielles Path-Traversal
   - Problem: Einstellungen für Pfade werden direkt an Pfadkonkatenation verwendet.
   - Maßnahme: Normierung (realpath), Verifikation dass Pfade innerhalb von `kimaiRootPath` liegen, Ablehnen von Einträgen mit `..` oder absoluten Pfaden.

5) Hoch: Logging & Fehlerbehandlung vereinheitlichen
   - Problem: Eigenes Logfile wird benutzt; keine PSR-3/Monolog-Integration.
   - Maßnahme: Integriere `logger` (PSR-3) via DI, konsistente Exception-Klassen, klare Unterscheidung zwischen Benutzer- und Systemfehlern.

6) Mittel: Tests & CI ausbauen
   - Problem: Einheitstests sind spärlich und enthalten Platzhalter.
   - Maßnahme: Tests für Service-Methoden (unit/integration), Mocking von Filesystem & Process, GitHub Actions für linting/phpstan/tests.

7) Mittel: Nutzung moderner Symfony APIs & Entfernen von Redundanzen
   - Problem: Dubletten von Konstanten/Logik zwischen Controller und Service.
   - Maßnahme: Gemeinsame Utility-Klasse/Value-Object für Konstanten und Pfad-Helper; Controller slimmer machen (delegieren an Service).

8) Mittel: Konfigurationsdefaults & Einstellungen überprüfen
   - Problem: `setting_backup_amount_max` fehlt als Default in DI; Verhalten unklar.
   - Maßnahme: Ergänze sinnvolle Defaults, dokumentiere alle Einstellungen in README und SystemConfiguration.

9) Niedrig: Verbesserte Nutzerführung / UI & Nachrichten
   - Problem: Flash-Messages sind generisch; Ergebnislogs sind in Datei und nicht im UI sichtbar (außer beim Anzeigen der Log-Datei).
   - Maßnahme: Zeige Zusammenfassung im UI nach Backup (Erfolg/Fehlercount), ermögliche Download des Logfiles, bessere Lokalisierung.

10) Niedrig: Performance & große Backups
   - Problem: Komplettes Spiegeln großer Verzeichnisse in temp-Ordner; speicher- und io-intensiv.
   - Maßnahme: Stream-basiertes Zippen, Ausschlussfilter, throttling, Option für inkrementelle Backups.

11) Wartung: Abhängigkeiten & Kompatibilität prüfen
   - Problem: README sagt nicht gewartet; dev-deps sind älter/in dev-only.
   - Maßnahme: Kompatibilitätsmatrix prüfen (Kimai-Versionen, PHP), composer.json aktualisieren, Sicherheits-Scans (Dependabot/GitHub-Actions).

12) Dokumentation: README und Restore-Warnungen verbessern
   - Problem: Restore-Warnung existiert, aber Prozesse/Empfehlungen fehlen.
   - Maßnahme: Schritt-für-Schritt Restore-Anleitung, Hinweise zu Backups in Produktionsumgebungen, Beispiel cronjobs, Sicherheits-Hinweise.

Vorschlag für nächsten Sprint (2–3 Tage):
- Sofort: Lockdown der Shell-Ausführung (Punkte 1+2) — minimal-invasive Änderungen, Umstellung auf Symfony Process.
- Anschließend: Safe-guard Restore & Pfad-Validierung (Punkte 3+4).
- Parallel: Tests + CI (Punkt 6) und Logging-Refactor (Punkt 5).
