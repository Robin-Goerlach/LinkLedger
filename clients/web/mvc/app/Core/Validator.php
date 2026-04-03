\
<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class Validator
 *
 * URL Validation + Canonicalization (Duplikat-Check)
 */
final class Validator
{
    /**
     * @return array{ok:bool, url:string, error:?string}
     */
    public static function validateUrl(string $input): array
    {
        $url = trim($input);
        if ($url === '') {
            return ['ok' => false, 'url' => $input, 'error' => 'URL ist leer.'];
        }

        // Scheme ergänzen (wie im Windows Client)
        if (!preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', $url)) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'url' => $input, 'error' => 'URL ist ungültig.'];
        }

        $p = parse_url($url);
        if (!$p || empty($p['scheme']) || empty($p['host'])) {
            return ['ok' => false, 'url' => $input, 'error' => 'URL muss Scheme und Host enthalten.'];
        }

        $scheme = strtolower((string)$p['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'url' => $input, 'error' => 'Nur http/https sind erlaubt.'];
        }

        if (preg_match('/\s/', (string)$p['host'])) {
            return ['ok' => false, 'url' => $input, 'error' => 'Host enthält ungültige Zeichen.'];
        }

        return ['ok' => true, 'url' => $url, 'error' => null];
    }

    public static function canonicalizeUrl(string $url): string
    {
        $p = parse_url($url);
        if (!$p || empty($p['host'])) return $url;

        $scheme = strtolower((string)($p['scheme'] ?? 'https'));
        $host = strtolower((string)$p['host']);

        $port = $p['port'] ?? null;
        if (($scheme === 'http' && $port == 80) || ($scheme === 'https' && $port == 443)) {
            $port = null;
        }

        $path = (string)($p['path'] ?? '');
        if ($path !== '/') $path = rtrim($path, '/');

        $query = isset($p['query']) ? '?' . $p['query'] : '';
        $portPart = $port ? ':' . (int)$port : '';

        return $scheme . '://' . $host . $portPart . $path . $query;
    }

    public static function sha256(string $text): string
    {
        return hash('sha256', $text);
    }
}
