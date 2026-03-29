# SASD Links – Windows Client (WPF) mit Export

Enthält:
- Wunderlist-Style 3-Pane UI
- Menü + Ribbon-ähnliche Toolbars
- URL-Validierung + Duplicate-Warnung
- Offline JSON Store unter %LOCALAPPDATA%\SasdLinksClient\
- Sync vorbereitet (Mock-Server via remote.json)
- Export:
  - Snapshot als JSON
  - Links als CSV (für Excel), optional nur aktuelles Projekt

## Öffnen in Visual Studio 2022
- `SasdLinks.sln` öffnen
- Startprojekt: `SasdLinks.Client`

## Export nutzen
- Menü: Datei -> Export -> JSON/CSV
- CSV enthält: project;url;title;description;tags
