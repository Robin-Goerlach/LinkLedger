<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class Auth
 *
 * Session-basierte Web-Auth.
 */
final class Auth
{
    public static function userId(): ?int
    {
        $uid = Session::get('user_id');
        if (is_int($uid)) return $uid;
        if (is_numeric($uid)) return (int)$uid;
        return null;
    }

    public static function requireLogin(App $app): int
    {
        $uid = self::userId();
        if (!$uid) {
            header('Location: ' . $app->url('/login'));
            exit;
        }
        return $uid;
    }
}
