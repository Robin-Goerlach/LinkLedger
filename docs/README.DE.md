# SASD LinkLedger – Windows Client (WPF) Demo

Eine leichtgewichtige Webanwendung zum Organisieren, Verschlagworten, Kommentieren, Suchen und Verwalten von URLs. Sie hilft Nutzern dabei, nützliche Links mit Titeln und Notizen zu speichern, Duplikate zu vermeiden und persönliche oder projektbezogene Webressourcen strukturiert und leicht auffindbar zu halten.
- Links: Projekte
- Mitte: Links (URLs) im Projekt + Suche + Tag-Filter
- Rechts: Link-Details + Tags (Dropdown) + kleine Tag-Verwaltung

## Ziele
1) Du bekommst ein Gefühl, wie die App "rund" wirkt.
2) Die App funktioniert **offline** (Local JSON Store).
3) Sync ist **vorbereitet**:
   - `MockApiClient` simuliert einen Server über `remote.json`
   - `HttpApiClient` ist als Platzhalter vorbereitet für die spätere PHP REST API

## Öffnen in Visual Studio 2022
- Öffne `SasdLinks.sln`.
- Starte das Projekt `SasdLinks.Client`.

## Hinweise
- TargetFramework ist `net8.0-windows`. Falls du noch kein .NET 8 SDK hast:
  - ändere in `src/SasdLinks.Client/SasdLinks.Client.csproj` auf `net6.0-windows`.

## Wo liegen die Daten?
Unter `%LOCALAPPDATA%\SasdLinksClient\`:
- `data.json` (lokaler Snapshot)
- `pending.json` (PendingOps für Sync)
- `remote.json` (Mock-Server Zustand)

## Duplikat-Erkennung
- pro Projekt: CanonicalUrl -> SHA256 -> CanonicalHash
- wenn ein Link mit gleicher CanonicalHash existiert: Status zeigt Warnung

## Nächste Schritte (wenn du willst)
1) Echte Tag-Administration als eigener Dialog (umbenennen, löschen mit Liste)
2) Linkliste: schöner (Icons, last updated, etc.)
3) Sync: echte REST Calls implementieren (`HttpApiClient`)
4) Konfliktlösung (falls Server & Client gleichzeitig ändern)
