<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class Router
 *
 * Minimal Router mit:
 * - BasePath stripping
 * - {param} in Patterns
 * - Debug::log(...) für Route-Matches
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable}> */
    private array $routes = [];
    private string $basePath = '';

    public function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(): void
    {
        $method = $this->effectiveMethod();
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $path = $uriPath;
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
            if ($path === '') $path = '/';
        }

        Debug::log('Dispatch', ['method' => $method, 'uri' => $uriPath, 'path' => $path, 'basePath' => $this->basePath]);

        foreach ($this->routes as $r) {
            if ($method !== $r['method']) continue;

            $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $r['pattern']);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                Debug::log('Route matched', ['pattern' => $r['pattern'], 'params' => $params]);
                $query = $_GET ?? [];
                ($r['handler'])($params, $query);
                return;
            }
        }

        Debug::log('No route matched', ['path' => $path]);
        http_response_code(404);
        echo "404 Not Found";
    }

    private function effectiveMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST') {
            $override = $_POST['_method'] ?? null;
            if (is_string($override)) {
                $override = strtoupper(trim($override));
                if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                    return $override;
                }
            }
        }
        return $method;
    }
}
