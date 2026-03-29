using System.Security.Cryptography;
using System.Text;

namespace SasdLinks.Core.Validation;

public static class UrlTools
{
    public static (bool ok, string normalizedUrl, string? error) Validate(string input)
    {
        var raw = (input ?? "").Trim();
        if (raw.Length == 0)
            return (false, input, "URL ist leer.");

        if (!raw.Contains("://", StringComparison.Ordinal))
            raw = "https://" + raw;

        if (!Uri.TryCreate(raw, UriKind.Absolute, out var uri))
            return (false, input, "URL ist ungültig (Uri.TryCreate fehlgeschlagen).");

        var scheme = uri.Scheme.ToLowerInvariant();
        if (scheme is not ("http" or "https"))
            return (false, input, "Nur http und https sind erlaubt.");

        if (string.IsNullOrWhiteSpace(uri.Host))
            return (false, input, "URL muss einen Host enthalten.");

        return (true, uri.ToString(), null);
    }

    public static string Canonicalize(string url)
    {
        if (!Uri.TryCreate(url, UriKind.Absolute, out var uri))
            return url;

        var builder = new UriBuilder(uri)
        {
            Scheme = uri.Scheme.ToLowerInvariant(),
            Host = uri.Host.ToLowerInvariant(),
            Fragment = ""
        };

        if ((builder.Scheme == "http" && builder.Port == 80) ||
            (builder.Scheme == "https" && builder.Port == 443))
        {
            builder.Port = -1;
        }

        var path = builder.Path ?? "";
        if (path.Length > 1 && path.EndsWith("/"))
            builder.Path = path.TrimEnd('/');

        return builder.Uri.ToString();
    }

    public static string Sha256Hex(string text)
    {
        var bytes = Encoding.UTF8.GetBytes(text ?? "");
        var hash = SHA256.HashData(bytes);
        var sb = new StringBuilder(hash.Length * 2);
        foreach (var b in hash)
            sb.Append(b.ToString("x2"));
        return sb.ToString();
    }
}
