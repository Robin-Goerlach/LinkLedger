using SasdLinks.Core.Models;
using SasdLinks.Core.Services;

namespace SasdLinks.Client.Services;

/// <summary>
/// Platzhalter für den späteren REST-Client gegen die PHP-API.
///
/// Idee:
/// - BaseUrl: z.B. https://domain.tld/sasd-links/public/api
/// - Token: Bearer Token (wie wir ihn bereits in der PHP-API angedacht haben)
///
/// Diese Klasse ist absichtlich noch nicht "fertig", damit du später die echten Endpoints eintragen kannst.
/// </summary>
public sealed class HttpApiClient : IApiClient
{
    private readonly HttpClient _http;
    private readonly string _baseUrl;
    private readonly string _token;

    public HttpApiClient(string baseUrl, string token)
    {
        _baseUrl = baseUrl.TrimEnd('/');
        _token = token;

        _http = new HttpClient();
        _http.DefaultRequestHeaders.Authorization =
            new System.Net.Http.Headers.AuthenticationHeaderValue("Bearer", _token);
    }

    public Task<Snapshot> DownloadSnapshotAsync(CancellationToken ct = default)
    {
        // Später: GET /sync/snapshot oder GET /projects + /links + /tags
        throw new NotImplementedException("HTTP API ist noch nicht verbunden. Nutze vorerst MockApiClient.");
    }

    public Task UploadOpsAsync(IEnumerable<PendingOp> ops, Snapshot localSnapshot, CancellationToken ct = default)
    {
        // Später: für jede PendingOp REST Call:
        // - Upsert project -> POST/PUT
        // - Delete project -> DELETE
        // usw.
        throw new NotImplementedException("HTTP API ist noch nicht verbunden. Nutze vorerst MockApiClient.");
    }
}
