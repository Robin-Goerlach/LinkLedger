<?php
declare(strict_types=1);

/**
 * Minimaler PSR-4 Autoloader (ohne Composer)
 * -----------------------------------------
 * Namespace:
 * - App\... => app/...
 *
 * Für produktive Projekte wäre Composer Standard – hier ist es bewusst minimal,
 * damit der Code gut nachvollziehbar bleibt.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $relative . '.php';

    if (is_file($file)) {
        require $file;
    }
});
