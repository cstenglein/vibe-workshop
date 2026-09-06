# Todo App

- Änderungen klein und fokussiert halten; bestehende PHP-/MySQL-Architektur verwenden.
- Für Features eigene Branches verwenden, keine direkten Änderungen auf main.
- Keine Secrets ins Repository, in Images oder Logs schreiben.
- Vor Abschluss `./scripts/test.sh` ausführen; nicht verfügbare Prüfungen benennen.
- Fehler nicht durch Entfernen von Tests lösen.
- Größere Architekturänderungen zuerst abstimmen.
- Bereits angewendete SQL-Migrationen nicht ändern; neue nummerierte, wiederholbare Migrationen hinzufügen.
- Vor einem Commit `git diff` prüfen.
- Änderungen und Testergebnisse kurz auf Deutsch zusammenfassen.
