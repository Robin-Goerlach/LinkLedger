<?php
declare(strict_types=1);

/**
 * app/entry.php
 * ============
 * Bootstrapping + Debug/Fehlerhandling.
 */

// 1) Config
$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    $configFile = __DIR__ . '/../config/config.example.php';
}
$config = require $configFile;

// 2) Autoload
require __DIR__ . '/Autoload.php';

// 3) Session
\App\Core\Session::start();

// 4) App helper
$app = new \App\Core\App($config['app']['base_path'] ?? null);

// 5) Debug init (vor DB)
$debugEnabled = (bool)($config['app']['debug'] ?? false);
$debugConsole = (bool)($config['app']['debug_console'] ?? false);
$logFile = $config['app']['log_file'] ?? null;

\App\Core\Debug::init($debugEnabled, $debugConsole, $app->requestId(), is_string($logFile) ? $logFile : null);

// 6) PHP error display
if ($debugEnabled) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// 7) Exception handler
set_exception_handler(function (Throwable $e) use ($debugEnabled, $app): void {
    \App\Core\Debug::exception($e, 'exception_handler');
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    if ($debugEnabled) {
        echo "<h1>500 – Exception</h1>";
        echo "<p><b>request_id:</b> " . htmlspecialchars($app->requestId()) . "</p>";
        echo "<pre>" . htmlspecialchars((string)$e) . "</pre>";
    } else {
        echo "Internal Server Error";
    }
});

// 8) Shutdown handler (fatale Fehler)
register_shutdown_function(function () use ($debugEnabled, $app): void {
    $err = error_get_last();
    if (!$err) return;

    \App\Core\Debug::log('Shutdown fatal error', $err);

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    if ($debugEnabled) {
        echo "<h1>500 – Fatal Error</h1>";
        echo "<p><b>request_id:</b> " . htmlspecialchars($app->requestId()) . "</p>";
        echo "<pre>" . htmlspecialchars(print_r($err, true)) . "</pre>";
    } else {
        echo "Internal Server Error";
    }
});

// 9) DB (inkl. Auto-Schema)
$db = new \App\Core\Database($config['db']);
$pdo = $db->pdo();

// 10) View + Models
$view = new \App\Core\View($app);

$users = new \App\Models\UserModel($pdo);
$projects = new \App\Models\ProjectModel($pdo);
$tags = new \App\Models\TagModel($pdo);
$links = new \App\Models\LinkModel($pdo);

// 11) Controllers
$authCtrl = new \App\Controllers\AuthController($app, $view, $users);
$appCtrl = new \App\Controllers\AppController($app, $view, $projects, $links, $tags);
$exportCtrl = new \App\Controllers\ExportController($app, $view, $projects, $tags, $links);

// 12) Router
$router = new \App\Core\Router();
$router->setBasePath($app->basePath());

$router->add('GET', '/', fn($p,$q) => $appCtrl->home());
$router->add('GET', '/app', fn($p,$q) => $appCtrl->show($p,$q));

$router->add('GET', '/login', fn($p,$q) => $authCtrl->showLogin());
$router->add('GET', '/register', fn($p,$q) => $authCtrl->showRegister());
$router->add('GET', '/logout', fn($p,$q) => $authCtrl->logout());

$router->add('GET', '/export/json', fn($p,$q) => $exportCtrl->exportJson($p,$q));
$router->add('GET', '/export/csv', fn($p,$q) => $exportCtrl->exportCsv($p,$q));

$router->add('POST', '/login', fn($p,$q) => $authCtrl->login());
$router->add('POST', '/register', fn($p,$q) => $authCtrl->register());

$router->add('POST', '/projects/create', fn($p,$q) => $appCtrl->createProject());
$router->add('POST', '/projects/delete', fn($p,$q) => $appCtrl->deleteProject());

$router->add('POST', '/links/save', fn($p,$q) => $appCtrl->saveLink());
$router->add('POST', '/links/delete', fn($p,$q) => $appCtrl->deleteLink());
$router->add('POST', '/links/new', fn($p,$q) => $appCtrl->newLinkMode());

$router->add('POST', '/tags/create', fn($p,$q) => $appCtrl->createTag());
$router->add('POST', '/tags/delete', fn($p,$q) => $appCtrl->deleteTag());

$router->add('POST', '/links/tags/add', fn($p,$q) => $appCtrl->addTagToLink());
$router->add('POST', '/links/tags/remove/{tagId}', fn($p,$q) => $appCtrl->removeTagFromLink($p));

// 13) Dispatch
$router->dispatch();
