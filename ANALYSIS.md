# EasyBackupBundle — Projektanalyse

Datum: 2026-02-23

Kurzbeschreibung
- Ein Kimai2 Plugin (Symfony Bundle) zum Erstellen und Wiederherstellen von Backups (ZIP + SQL Dump).
- Version: 2.0.5 (composer.json)

Projektstruktur (Wichtigste Dateien)
- `composer.json` — Metadaten, PHP 8.1 Plattform, dev-Dependencies (phpstan, php-cs-fixer, symfony/* ^6.0).
- `EasyBackupBundle.php` — Bundle-Registrierung (`PluginInterface`).
- `Command/EasyBackupBackupCommand.php` — CLI-Kommando `EasyBackup:backup`.
- `Service/EasyBackupService.php` — Kernlogik: Backup erstellen, DB-Dump, Zip, Aufräumen, Löschen alter Backups.
- `Controller/EasyBackupController.php` — Web-UI: Erstellen, Download, Restore, PrepareRecovery, Delete.
- `DependencyInjection/Configuration.php` und `EasyBackupExtension.php` — Bundle-Konfiguration + Prepend (Permissions).
- `Configuration/EasyBackupConfiguration.php` — Wrapper für SystemConfiguration (Zugriff auf Einstellungen).
- `Resources/config/services.yaml` & `routes.yaml` — Service- und Routenkonfiguration.
- `Readme.md` — Nutzung, Installationshinweise, Hinweise zu Backups.
- `Tests/Command/EasyBackupBackupCommandTest.php` — einfacher Test (Platzhalter-Ausgabe).

Funktionsübersicht
- Backup-Erstellung (Service):
  - Erstellt ein temporäres Verzeichnis `var/easy_backup/<timestamp>/`.
  - Schreibt `manifest.json` mit Kimai-Version und Git-Commit (falls verfügbar via `git rev-parse HEAD`).
  - Kopiert/Spiegelt konfigurierte Pfade (per Zeilen in Setting) ins temporäre Verzeichnis.
  - Legt für MySQL/MariaDB einen SQL-Dump via ein konfigurierbares `mysqldump`-Kommando an (ersetzen von {user}/{password}/... mit Werten aus `DATABASE_URL`).
  - Zippt das temporäre Verzeichnis und entfernt temporäre Dateien.
  - Löscht alte Backups (konfigurierbar über Setting key `setting_backup_amount_max` — im Code vorhanden, default fehlt in DI).

- Restore (Controller):
  - Entpackt ZIP in temporäres Verzeichnis, führt `restoreMySQLDump` und `restoreDirsAndFiles` aus.
  - `restoreDirsAndFiles` iteriert Backup-Dateien und versucht, diese in die Kimai-Root-Pfade zurückzuschreiben (Blacklist: manifest.json, database_dump.sql).

Konfiguration & DI
- Default-Konfiguration ist in `DependencyInjection/Configuration.php` hinterlegt (mysqldump, restore command, backup dir `var/easy_backup/`, default-Pfade zum Sichern).
- `EasyBackupExtension::prepend()` fügt eine Permission `easy_backup` zu `ROLE_SUPER_ADMIN` hinzu.
- Services sind autowired/autoconfigured; Controller werden als service_arguments registriert.

Tests & Qualitätstools
- Composer-Scripts enthalten `phpstan` und php-cs-fixer Befehle; `phpstan.neon` ist vorhanden (inkl. phpstan-symfony/phpstan-doctrine includes).
- Es gibt einen sehr einfachen KernelTest-Case mit einem Platzhalterassert.

Sicherheit & Risiken (erste Eindrücke)
- Ausführung von externen Shell-Kommandos:
  - `exec(self::CMD_GIT_HEAD, ...)` in Service/Controller: kann fehlschlagen, wird einigermaßen geloggt.
  - DB-Dump: erzeugt ein Kommando aus Template + escapeshellarg für einzelne Variablen, aber anschließend wird `execute()` aufgerufen, das `proc_open(escapeshellcmd($cmd), ...)` nutzt — `escapeshellcmd` kann Teile verändern und ist nicht gleichbedeutend mit sicherer Parametrisierung. Risiko: command injection möglich, wenn `DATABASE_URL` enthält, was besser validiert werden muss.
- Nutzung von `$_SERVER['DATABASE_URL']` direkt in Konstruktoren (keine Kontrolle über Fehlen oder Manipulation).
- File handling / Pfad-Manipulation:
  - Pfadgenerierung via String-Konkatenation mit `kimaiRootPath` und ungeprüften Einträgen aus Einstellungen — Risiko von Pfad-Traversal (wenn Settings manipuliert werden).
  - Restore-Funktion überschreibt Dateien im Filesystem; es gibt nur eine Blacklist für zwei Dateinamen.
  - Download/Deletion: filename-Parameter wird via Regex validiert (gute Praxis), allerdings wird in einigen Stellen basename / realpath gemischt — prüfen, ob Race-Conditions möglich sind.
- Logging: einfache logfile-basierte Logs in `var/easy_backup/easybackup.log` (keine zentrale Logger-API). Fehlermeldungen werden oft via `flashError`/`flashSuccess` im Controller ausgegeben.

Code-Qualität & Wartbarkeit
- Redundanzen zwischen Service und Controller (gleiche Konstante-Definitionen: z.B. CMD_GIT_HEAD, MANIFEST_FILENAME etc.). Könnte in zentrale Utility/DTO ausgelagert werden.
- Direkte Abhängigkeit auf globale Server-Variablen (`$_SERVER`) in mehreren Konstruktoren erschwert Testbarkeit.
- Fehlerbehandlung: vielerorts werden RuntimeException oder Flash-Messages verwendet; nicht konsistente Verwendung von Logging und Exceptions.
- Tests sind dünn; kein CI/Workflow erkennbar (keine GitHub Actions in repo).

Abhängigkeiten & Kompatibilität
- composer.json setzt `php` platform auf `8.1`.
- dev deps listen `symfony/console` & `event-dispatcher` ^6.0 — vermutlich für Entwicklung, Produktions-compat hängt von Kimai-Host.
- README sagt Plugin ist nicht mehr aktiv gewartet (Hinweis aus Readme).

Offene Punkte / Beobachtungen
- Der Default für `setting_backup_amount_max` wird im Configuration-Tree nicht gesetzt (Code erwartet Key in `EasyBackupConfiguration::getBackupAmountMax()` — Default logic gibt -1 wenn nicht gesetzt). Prüfen, ob Lösch-Policy richtig funktioniert.
- In `execute()` wird `proc_open(escapeshellcmd($cmd), ...)` verwendet — sollte überprüft und ggf. auf sichere Prozessaufrufe umgestellt werden.
- Test `EasyBackupBackupCommandTest.php` enthält Platzhalter-Assertion (`Some output`) — Test wird fehlschlagen, wenn nicht aktualisiert.

Fazit
- Das Bundle liefert die Kernfunktionalität (Backup/Restore) vollständig und pragmatisch.
- Es gibt mehrere sicherheits- und wartbarkeitsrelevante Punkte (Shell-Ausführung, direkte Nutzung von globals, Pfad-Operationen, Restore-Überschreiben) die priorisiert angegangen werden sollten.

Weitere Schritte
- Siehe `IMPROVEMENTS_PRIORITIZED.md` (priorisierte Liste mit vorgeschlagenen Änderungen).
