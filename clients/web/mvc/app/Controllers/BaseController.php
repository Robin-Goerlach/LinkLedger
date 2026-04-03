<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\View;
use App\Core\Session;
use App\Core\Debug;

/**
 * Class BaseController
 *
 * Gemeinsame Helfer für Controller:
 * - redirect()
 * - requireCsrf()
 */
abstract class BaseController
{
    public function __construct(protected App $app, protected View $view) {}

    protected function redirect(string $path, array $query = []): void
    {
        $url = $this->app->url($path);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        Debug::log('Redirect', ['to' => $url]);
        header('Location: ' . $url);
        exit;
    }

    protected function requireCsrf(): void
    {
        Session::start();
        if (!Session::checkCsrf($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'CSRF Token ungültig. Bitte Seite neu laden.');
            $this->redirect('/app');
        }
    }
}
