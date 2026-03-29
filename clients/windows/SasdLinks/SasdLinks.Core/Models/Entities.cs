using System.Text.Json.Serialization;

namespace SasdLinks.Core.Models;

/// <summary>
/// In dieser Demo arbeiten wir mit GUIDs als IDs.
/// Vorteil: Der Client kann Objekte offline anlegen, ohne vorher eine DB-ID vom Server zu bekommen.
/// Beim späteren Sync lassen sich GUIDs stabil mappen.
/// </summary>
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

    /// <summary>Original URL wie der Nutzer sie eingibt (nach Validierung ggf. mit ergänzt. Scheme).</summary>
    public string Url { get; set; } = "";

    /// <summary>
    /// Kanonische/normalisierte URL zur Duplikat-Erkennung.
    /// Beispiel: https://example.com/docs/  -> https://example.com/docs
    /// </summary>
    public string CanonicalUrl { get; set; } = "";

    /// <summary>SHA256(CanonicalUrl). In der PHP-App war das wegen MySQL UNIQUE auf TEXT hilfreich.</summary>
    public string CanonicalHash { get; set; } = "";

    public string? Title { get; set; }
    public string? Description { get; set; }

    /// <summary>
    /// Tags als Liste von Tag-IDs (viele-zu-viele, aber für den Client genügt hier eine einfache Liste).
    /// </summary>
    public List<Guid> TagIds { get; set; } = new();
}

/// <summary>
/// Eine Operation, die lokal ausgeführt wurde und später zum Server synchronisiert werden soll.
/// V1: Wir syncen als "Upsert" (Insert/Update) und "Delete".
/// </summary>
public sealed class PendingOp
{
    public Guid Id { get; set; } = Guid.NewGuid();
    public DateTime CreatedAtUtc { get; set; } = DateTime.UtcNow;

    public PendingOpType Type { get; set; }
    public Guid EntityId { get; set; }         // Project/Link/Tag Id
    public string EntityKind { get; set; } = ""; // "project" | "link" | "tag"
}

public enum PendingOpType
{
    Upsert = 1,
    Delete = 2
}

/// <summary>
/// Snapshot für die Synchronisation: "Server liefert gesamte Welt" (simple, nicht optimal).
/// Später kann man auf Delta-Sync umstellen.
/// </summary>
public sealed class Snapshot
{
    public DateTime GeneratedAtUtc { get; set; } = DateTime.UtcNow;
    public List<Project> Projects { get; set; } = new();
    public List<LinkItem> Links { get; set; } = new();
    public List<Tag> Tags { get; set; } = new();
}
