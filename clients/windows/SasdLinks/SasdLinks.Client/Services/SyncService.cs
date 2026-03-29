using SasdLinks.Core.Models;
using SasdLinks.Core.Services;

namespace SasdLinks.Client.Services;

public sealed class SyncService : ISyncService
{
    private readonly ILocalStore _local;
    private readonly IApiClient _api;

    public SyncService(ILocalStore local, IApiClient api)
    {
        _local = local;
        _api = api;
    }

    public async Task<SyncResult> SyncAsync(CancellationToken ct = default)
    {
        var pending = await _local.LoadPendingOpsAsync(ct);
        var snap = await _local.LoadAsync(ct);

        try
        {
            await _api.UploadOpsAsync(pending, snap, ct);
            var serverSnap = await _api.DownloadSnapshotAsync(ct);

            await _local.SaveAsync(serverSnap, ct);
            var uploaded = pending.Count;
            pending.Clear();
            await _local.SavePendingOpsAsync(pending, ct);

            return new SyncResult
            {
                Ok = true,
                Message = "Synchronisation erfolgreich (Mock).",
                UploadedOps = uploaded
            };
        }
        catch (Exception ex)
        {
            return new SyncResult
            {
                Ok = false,
                Message = "Sync fehlgeschlagen: " + ex.Message
            };
        }
    }
}
