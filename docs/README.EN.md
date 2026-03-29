# SASD LinkLedger – Windows Client (WPF) Demo

A lightweight web application for organizing, tagging, commenting, searching, and managing URLs. It helps users store useful links with titles and notes, avoid duplicates, and keep their personal or project-related web resources structured and easy to find.

- Left: Projects
- Center: Links (URLs) within the selected project + search + tag filter
- Right: Link details + tags (dropdown) + small tag management area

## Goals
1. Give you a feeling for how the app feels as a whole.
2. The app works **offline** (local JSON store).
3. Sync is **prepared**:
   - `MockApiClient` simulates a server using `remote.json`
   - `HttpApiClient` is prepared as a placeholder for the future PHP REST API

## Open in Visual Studio 2022
- Open `SasdLinks.sln`.
- Start the project `SasdLinks.Client`.

## Notes
- The target framework is `net8.0-windows`. If you do not yet have the .NET 8 SDK:
  - change `src/SasdLinks.Client/SasdLinks.Client.csproj` to `net6.0-windows`.

## Where is the data stored?
Under `%LOCALAPPDATA%\SasdLinksClient\`:
- `data.json` (local snapshot)
- `pending.json` (pending operations for sync)
- `remote.json` (mock server state)

## Duplicate Detection
- Per project: CanonicalUrl -> SHA256 -> CanonicalHash
- If a link with the same CanonicalHash already exists, the status displays a warning

## Next Steps (if you want)
1. Real tag management as a separate dialog (rename, delete with list view)
2. Improve the link list visually (icons, last updated, etc.)
3. Implement real REST calls for sync (`HttpApiClient`)
4. Conflict resolution (if server and client are changed at the same time)
