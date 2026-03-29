using System.Text;
using System.Text.Json;
using SasdLinks.Core.Models;

namespace SasdLinks.Client.Services;

/// <summary>
/// Exporter:
/// - JSON Export: Snapshot als portable Datei
/// - CSV Export: Links (für Excel)
///
/// Hinweis: Export soll niemals Login/Token enthalten (haben wir im Snapshot nicht).
/// </summary>
public static class Exporters
{
    private static readonly JsonSerializerOptions JsonOptions = new() { WriteIndented = true };

    public static string ToJson(Snapshot snapshot)
        => JsonSerializer.Serialize(snapshot, JsonOptions);

    public static string LinksToCsv(
        Snapshot snapshot,
        Guid? projectId = null)
    {
        var sb = new StringBuilder();

        // Header
        sb.AppendLine("project;url;title;description;tags");

        // Hilfsmap TagId -> Name
        var tagMap = snapshot.Tags.ToDictionary(t => t.Id, t => t.Name);

        IEnumerable<LinkItem> links = snapshot.Links;
        if (projectId.HasValue)
            links = links.Where(l => l.ProjectId == projectId.Value);

        foreach (var l in links.OrderByDescending(x => x.UpdatedAtUtc))
        {
            var projectName = snapshot.Projects.FirstOrDefault(p => p.Id == l.ProjectId)?.Name ?? "";
            var tags = string.Join(",", l.TagIds.Select(id => tagMap.TryGetValue(id, out var n) ? n : "").Where(x => x.Length > 0));

            sb.AppendLine(string.Join(";",
                Csv(projectName),
                Csv(l.Url),
                Csv(l.Title ?? ""),
                Csv(l.Description ?? ""),
                Csv(tags)
            ));
        }

        return sb.ToString();
    }

    /// <summary>
    /// Einfache CSV-Escaping-Regel:
    /// - wenn ;, \n oder " vorkommt -> in "..." einschließen und " verdoppeln
    /// </summary>
    private static string Csv(string value)
    {
        value ??= "";
        var needs = value.Contains(';') || value.Contains('\n') || value.Contains('"');
        if (!needs) return value;

        return """ + value.Replace(""", """") + """;
    }
}
