<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class App
 *
 * Stellt zentrale Hilfen bereit:
 * - basePath (Subdirectory Hosting)
 * - requestId (Debug-Korrelation)
 * - url() Helper
 */
final class App
{
    private string $basePath;
    private string $requestId;

    public function __construct(?string $configuredBasePath = null)
    {
        $this->basePath = rtrim($configuredBasePath ?? $this->guessBasePath(), '/');
        $this->requestId = bin2hex(random_bytes(8));
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function url(string $path): string
    {
        if ($path === '') $path = '/';
        if ($path[0] !== '/') $path = '/' . $path;
        return $this->basePath . $path;
    }

    private function guessBasePath(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = preg_replace('#/index\.php$#', '', $script);
        return $base ?: '';
    }
}
