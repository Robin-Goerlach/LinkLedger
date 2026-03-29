using System.Text.Json.Serialization;

namespace SasdLinks.Core.Models;

public abstract class EntityBase
{
    public Guid Id { get; set; } = Guid.NewGuid();
    public DateTime CreatedAtUtc { get; set; } = DateTime.UtcNow;
    public DateTime UpdatedAtUtc { get; set; } = DateTime.UtcNow;
}

public sealed class Project : EntityBase
{
    public string Name { get; set; } = "";
    public string? Description { get; set; }
}

public sealed class Tag : EntityBase
{
    public string Name { get; set; } = "";
}

public sealed class LinkItem : EntityBase
{
    public Guid ProjectId { get; set; }
    public string Url { get; set; } = "";
    public string CanonicalUrl { get; set; } = "";
    public string CanonicalHash { get; set; } = "";
    public string? Title { get; set; }
    public string? Description { get; set; }
    public List<Guid> TagIds { get; set; } = new();
}

public sealed class PendingOp
{
    public Guid Id { get; set; } = Guid.NewGuid();
    public DateTime CreatedAtUtc { get; set; } = DateTime.UtcNow;
    public PendingOpType Type { get; set; }
    public Guid EntityId { get; set; }
    public string EntityKind { get; set; } = "";
}

public enum PendingOpType
{
    Upsert = 1,
    Delete = 2
}

/// <summary>
/// Snapshot ist der komplette Datenstand (Offline First).
/// Wichtig für Updates: Wir führen eine SchemaVersion, damit Migrationen möglich sind.
/// </summary>
public sealed class Snapshot
{
    public int SchemaVersion { get; set; } = 1;
    public DateTime GeneratedAtUtc { get; set; } = DateTime.UtcNow;
    public List<Project> Projects { get; set; } = new();
    public List<LinkItem> Links { get; set; } = new();
    public List<Tag> Tags { get; set; } = new();
}
