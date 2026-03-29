using System.Text.Json;
using SasdLinks.Core.Models;
using SasdLinks.Core.Services;

namespace SasdLinks.Client.Services;

public sealed class MockApiClient : IApiClient
{
    private readonly string _remoteFile;
    private static readonly JsonSerializerOptions JsonOptions = new() { WriteIndented = true };

    public MockApiClient()
    {
        var folder = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "SasdLinksClient"
        );
        Directory.CreateDirectory(folder);

        _remoteFile = Path.Combine(folder, "remote.json");
        if (!File.Exists(_remoteFile))
        {
            var seed = new Snapshot();
            File.WriteAllText(_remoteFile, JsonSerializer.Serialize(seed, JsonOptions));
        }
    }

    public async Task<Snapshot> DownloadSnapshotAsync(CancellationToken ct = default)
    {
        var json = await File.ReadAllTextAsync(_remoteFile, ct);
        return JsonSerializer.Deserialize<Snapshot>(json, JsonOptions) ?? new Snapshot();
    }

    public async Task UploadOpsAsync(IEnumerable<PendingOp> ops, Snapshot localSnapshot, CancellationToken ct = default)
    {
        var snap = localSnapshot;
        snap.GeneratedAtUtc = DateTime.UtcNow;

        var json = JsonSerializer.Serialize(snap, JsonOptions);
        await File.WriteAllTextAsync(_remoteFile, json, ct);
    }
}
