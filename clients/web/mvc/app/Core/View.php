<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class View
 *
 * Mini-View Engine:
 * - layout.php enthält Grundlayout und inkludiert Content-Template.
 */
final class View
{
    public function __construct(private App $app) {}

    public function render(string $template, array $vars = []): void
    {
        Session::start();

        $vars['_app'] = $this->app;
        $vars['_flash'] = Session::consumeFlash();
        $vars['_csrf'] = Session::csrfToken();

        extract($vars, EXTR_SKIP);

        $contentTemplate = __DIR__ . '/../Views/' . $template . '.php';
        $layout = __DIR__ . '/../Views/layout.php';

        if (!is_file($contentTemplate)) {
            http_response_code(500);
            echo "View not found: " . htmlspecialchars($template);
            exit;
        }

        include $layout;
        exit;
    }
}
