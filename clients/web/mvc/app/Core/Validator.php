<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class Validator
 *
 * Sehr strikte URL-Validierung, weil du das explizit wolltest.
 * Zusätzlich canonicalizeUrl + sha256 => Duplikat-Erkennung.
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
            Debug::log('URL validation failed: empty');
            return ['ok' => false, 'url' => $input, 'error' => 'URL ist leer.'];
        }

        // Scheme ergänzen (wie Windows Client)
        if (!preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', $url)) {
            Debug::log('URL scheme missing -> prepend https://', ['input' => $url]);
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Debug::log('URL validation failed: FILTER_VALIDATE_URL', ['url' => $url]);
            return ['ok' => false, 'url' => $input, 'error' => 'URL ist ungültig.'];
        }

        $p = parse_url($url);
        if (!$p || empty($p['scheme']) || empty($p['host'])) {
            Debug::log('URL validation failed: parse_url missing scheme/host', ['url' => $url]);
            return ['ok' => false, 'url' => $input, 'error' => 'URL muss Scheme und Host enthalten.'];
        }

        $scheme = strtolower((string)$p['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            Debug::log('URL validation failed: scheme not allowed', ['scheme' => $scheme]);
            return ['ok' => false, 'url' => $input, 'error' => 'Nur http/https sind erlaubt.'];
        }

        if (preg_match('/\s/', (string)$p['host'])) {
            Debug::log('URL validation failed: whitespace in host', ['host' => $p['host']]);
            return ['ok' => false, 'url' => $input, 'error' => 'Host enthält ungültige Zeichen.'];
        }

        Debug::log('URL validation ok', ['normalized' => $url]);
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

        $canon = $scheme . '://' . $host . $portPart . $path . $query;
        Debug::log('URL canonicalized', ['canonical' => $canon]);
        return $canon;
    }

    public static function sha256(string $text): string
    {
        return hash('sha256', $text);
    }
}
