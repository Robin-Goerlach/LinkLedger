<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class App
 *
 * Hilfsfunktionen:
 * - basePath Handling (Subdirectory Hosting)
 * - requestId (Debug)
 * - url() Helper
 */
final class App
{
    private string $basePath;
    private string $requestId;

    public function __construct(?string $configuredBasePath = null)
    {
        $this->basePath = $configuredBasePath ?? $this->guessBasePath();
        $this->basePath = rtrim($this->basePath, '/');
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

    /**
     * Baut eine URL inkl. BasePath.
     *
     * @param string $path z.B. '/app' oder 'login'
     */
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
