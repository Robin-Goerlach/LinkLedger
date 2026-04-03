<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class Session
 *
 * - Session starten
 * - Flash Messages
 * - Flash Data (Form-Werte nach Redirect)
 * - CSRF Token
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<int, array{type:string, message:string}>
     */
    public static function consumeFlash(): array
    {
        $msgs = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($msgs) ? $msgs : [];
    }

    public static function flashData(string $key, mixed $value): void
    {
        $_SESSION['_flash_data'][$key] = $value;
    }

    public static function consumeFlashData(string $key, mixed $default = null): mixed
    {
        $val = $_SESSION['_flash_data'][$key] ?? $default;
        unset($_SESSION['_flash_data'][$key]);
        return $val;
    }

    public static function csrfToken(): string
    {
        $t = $_SESSION['_csrf'] ?? '';
        if (!is_string($t) || $t === '') {
            $t = bin2hex(random_bytes(16));
            $_SESSION['_csrf'] = $t;
        }
        return $t;
    }

    public static function checkCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals((string)($_SESSION['_csrf'] ?? ''), $token);
    }
}
