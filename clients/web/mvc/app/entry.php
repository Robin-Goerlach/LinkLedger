\
<?php
declare(strict_types=1);

/**
 * app/entry.php
 * -------------
 * Gemeinsamer Bootstrapping-Code für Root index.php und public/index.php.
 */

$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    $configFile = __DIR__ . '/../config/config.example.php';
}
$config = require $configFile;

require __DIR__ . '/Autoload.php';

\App\Core\Session::start();

$app = new \App\Core\App($config['app']['base_path'] ?? null);

$db = new \App\Core\Database($config['db']);
$pdo = $db->pdo();

$view = new \App\Core\View($app);

$users = new \App\Models\UserModel($pdo);
$projects = new \App\Models\ProjectModel($pdo);
$tags = new \App\Models\TagModel($pdo);
$links = new \App\Models\LinkModel($pdo);

$authCtrl = new \App\Controllers\AuthController($app, $view, $users);
$appCtrl = new \App\Controllers\AppController($app, $view, $projects, $links, $tags);
$exportCtrl = new \App\Controllers\ExportController($app, $view, $projects, $tags, $links);

$router = new \App\Core\Router();
$router->setBasePath($app->basePath());

// GET
$router->add('GET', '/', fn($p,$q) => $appCtrl->home());
$router->add('GET', '/app', fn($p,$q) => $appCtrl->show($p,$q));

$router->add('GET', '/login', fn($p,$q) => $authCtrl->showLogin());
$router->add('GET', '/register', fn($p,$q) => $authCtrl->showRegister());
$router->add('GET', '/logout', fn($p,$q) => $authCtrl->logout());

$router->add('GET', '/export/json', fn($p,$q) => $exportCtrl->exportJson($p,$q));
$router->add('GET', '/export/csv', fn($p,$q) => $exportCtrl->exportCsv($p,$q));

// POST
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

try {
    $router->dispatch();
} catch (Throwable $e) {
    $debug = (bool)($config['app']['debug'] ?? false);
    http_response_code(500);
    if ($debug) {
        echo "<pre>Uncaught exception\n\n" . htmlspecialchars((string)$e) . "</pre>";
    } else {
        echo "Internal Server Error";
    }
}
