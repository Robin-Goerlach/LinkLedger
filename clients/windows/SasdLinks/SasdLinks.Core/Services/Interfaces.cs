using SasdLinks.Core.Models;

namespace SasdLinks.Core.Services;

/// <summary>
/// Lokaler Store: Speichert Daten offline (hier JSON-Datei).
/// </summary>
public interface ILocalStore
{
    Task<Snapshot> LoadAsync(CancellationToken ct = default);
    Task SaveAsync(Snapshot snapshot, CancellationToken ct = default);

    Task<List<PendingOp>> LoadPendingOpsAsync(CancellationToken ct = default);
    Task SavePendingOpsAsync(List<PendingOp> ops, CancellationToken ct = default);
}

/// <summary>
/// API-Client: später echtes REST gegen PHP. Aktuell Mock.
/// </summary>
public interface IApiClient
{
    Task<Snapshot> DownloadSnapshotAsync(CancellationToken ct = default);
    Task UploadOpsAsync(IEnumerable<PendingOp> ops, Snapshot localSnapshot, CancellationToken ct = default);
}

/// <summary>
/// Sync-Service: orchestriert Upload (ops) und Download (snapshot).
/// </summary>
public interface ISyncService
{
    Task<SyncResult> SyncAsync(CancellationToken ct = default);
}

public sealed class SyncResult
{
    public bool Ok { get; set; }
    public string Message { get; set; } = "";
    public int UploadedOps { get; set; }
}
