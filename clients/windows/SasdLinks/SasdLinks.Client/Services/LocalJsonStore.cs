using System.Text;
using System.Text.Json;
using SasdLinks.Core.Models;
using SasdLinks.Core.Services;

namespace SasdLinks.Client.Services;

public sealed class LocalJsonStore : ILocalStore
{
    private readonly string _folder;
    private readonly string _dataFile;
    private readonly string _pendingFile;

    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        WriteIndented = true
    };

    public LocalJsonStore()
    {
        _folder = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "SasdLinksClient"
        );
        Directory.CreateDirectory(_folder);

        _dataFile = Path.Combine(_folder, "data.json");
        _pendingFile = Path.Combine(_folder, "pending.json");
    }

    public async Task<Snapshot> LoadAsync(CancellationToken ct = default)
    {
        if (!File.Exists(_dataFile))
            return SeedEmpty();

        var json = await File.ReadAllTextAsync(_dataFile, ct);
        var snap = JsonSerializer.Deserialize<Snapshot>(json, JsonOptions);
        return snap ?? SeedEmpty();
    }

    public async Task SaveAsync(Snapshot snapshot, CancellationToken ct = default)
    {
        snapshot.GeneratedAtUtc = DateTime.UtcNow;

        // Atomic write: erst in tmp schreiben, dann replace.
        var tmp = _dataFile + ".tmp";
        var backup = _dataFile + ".bak";

        var json = JsonSerializer.Serialize(snapshot, JsonOptions);
        await File.WriteAllTextAsync(tmp, json, new UTF8Encoding(encoderShouldEmitUTF8Identifier: false), ct);

        if (File.Exists(_dataFile))
            File.Copy(_dataFile, backup, overwrite: true);

        // Replace ist auf Windows atomischer als Copy+Delete
        File.Copy(tmp, _dataFile, overwrite: true);
        File.Delete(tmp);
    }

    public async Task<List<PendingOp>> LoadPendingOpsAsync(CancellationToken ct = default)
    {
        if (!File.Exists(_pendingFile))
            return new List<PendingOp>();

        var json = await File.ReadAllTextAsync(_pendingFile, ct);
        var ops = JsonSerializer.Deserialize<List<PendingOp>>(json, JsonOptions);
        return ops ?? new List<PendingOp>();
    }

    public async Task SavePendingOpsAsync(List<PendingOp> ops, CancellationToken ct = default)
    {
        var json = JsonSerializer.Serialize(ops, JsonOptions);
        await File.WriteAllTextAsync(_pendingFile, json, ct);
    }

    private static Snapshot SeedEmpty()
    {
        var p = new Project { Name = "Inbox", Description = "Standard-Projekt (Demo)" };
        var t = new Tag { Name = "sample" };

        return new Snapshot
        {
            SchemaVersion = 1,
            Projects = new() { p },
            Tags = new() { t },
            Links = new()
        };
    }
}
