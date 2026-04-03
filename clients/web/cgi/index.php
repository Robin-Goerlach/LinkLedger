<?php
declare(strict_types=1);

/**
 * LinkLedger – CGI-Style Single Script (PHP 8.2)
 * =============================================
 * Idee wie "PHP 3 / Perl CGI":
 *  - Request rein
 *  - Script läuft top-to-bottom
 *  - DB connect -> work -> HTML -> Ende
 *
 * Keine .htaccess erforderlich.
 */

// ------------------------------------------------------------
// 0) Minimale Laufzeit-Checks
// ------------------------------------------------------------
if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "LinkLedger benötigt PHP 8.2+.\nAktuell: " . PHP_VERSION . "\n";
    exit;
}

// ------------------------------------------------------------
// 1) .env laden (minimaler Loader ohne Composer)
// ------------------------------------------------------------

/**
 * Liest eine .env Datei im Projekt-Root ein.
 * Unterstützt KEY=VALUE (ohne Quotes/mit Quotes).
 *
 * @param string $filePath
 * @return array<string,string>
 */
function load_env(string $filePath): array
{
    if (!is_file($filePath)) {
        return [];
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return [];
    }

    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Split at first "="
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        // Remove optional quotes
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }

        $env[$key] = $val;
        $_ENV[$key] = $val;
    }

    return $env;
}

/**
 * Env helper.
 */
function env(string $key, ?string $default = null): ?string
{
    $v = $_ENV[$key] ?? getenv($key);
    if ($v === false || $v === null) {
        return $default;
    }
    return (string)$v;
}

load_env(__DIR__ . '/.env');

$APP_DEBUG = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
$APP_DEBUG_CONSOLE = filter_var(env('APP_DEBUG_CONSOLE', 'false'), FILTER_VALIDATE_BOOLEAN);
$APP_BASE_PATH = rtrim((string)env('APP_BASE_PATH', ''), '/');  // z.B. /linkledger
$APP_LOG_FILE = env('APP_LOG_FILE', __DIR__ . '/storage/logs/app.log');

// PHP error reporting
if ($APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

// ------------------------------------------------------------
// 2) Debug Logger (console.log + optional file)
// ------------------------------------------------------------

$requestId = bin2hex(random_bytes(8));

/** @var array<int, array{ts:string,msg:string,ctx:array<string,mixed>}> */
$DEBUG_LOGS = [];

/**
 * @param string $msg
 * @param array<string,mixed> $ctx
 */
function dbg(string $msg, array $ctx = []): void
{
    global $APP_DEBUG, $APP_LOG_FILE, $requestId, $DEBUG_LOGS;

    if (!$APP_DEBUG) {
        return;
    }

    $row = [
        'ts' => gmdate('c'),
        'msg' => $msg,
        'ctx' => $ctx,
    ];
    $DEBUG_LOGS[] = $row;

    // File log (best effort)
    if ($APP_LOG_FILE) {
        $dir = dirname($APP_LOG_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($APP_LOG_FILE, '[' . $row['ts'] . '][' . $requestId . '] ' . $msg . ' ' . json_encode($ctx) . PHP_EOL, FILE_APPEND);
    }
}

/**
 * Rendert alle Debug Logs als JS console.log.
 */
function render_console_logs(): string
{
    global $APP_DEBUG, $APP_DEBUG_CONSOLE, $DEBUG_LOGS, $requestId;
    if (!$APP_DEBUG || !$APP_DEBUG_CONSOLE) {
        return '';
    }

    $payload = json_encode([
        'requestId' => $requestId,
        'count' => count($DEBUG_LOGS),
        'logs' => $DEBUG_LOGS,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!$payload) {
        return '<script>console.log("LinkLedger debug: json_encode failed");</script>';
    }

    return "<script>(function(){const p=$payload; const prefix='[LinkLedger]['+p.requestId+']'; console.groupCollapsed(prefix+' logs ('+p.count+')'); for(const r of p.logs){console.log(prefix,r.ts,r.msg,r.ctx);} console.groupEnd();})();</script>";
}

/**
 * Baut URL zu diesem Script (mit base_path).
 *
 * @param array<string,mixed> $params
 */
function url(array $params = []): string
{
    global $APP_BASE_PATH;

    // CGI-like: immer index.php?...
    $base = $APP_BASE_PATH . '/index.php';
    if (empty($params)) {
        return $base;
    }
    return $base . '?' . http_build_query($params);
}

// ------------------------------------------------------------
// 3) Session + Flash + CSRF
// ------------------------------------------------------------
session_start();

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * @return array<int, array{type:string,message:string}>
 */
function consume_flash(): array
{
    $msgs = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($msgs) ? $msgs : [];
}

function flash_data(string $key, mixed $value): void
{
    $_SESSION['_flash_data'][$key] = $value;
}

function consume_flash_data(string $key, mixed $default = null): mixed
{
    $val = $_SESSION['_flash_data'][$key] ?? $default;
    unset($_SESSION['_flash_data'][$key]);
    return $val;
}

function csrf_token(): string
{
    $t = $_SESSION['_csrf'] ?? '';
    if (!is_string($t) || $t === '') {
        $t = bin2hex(random_bytes(16));
        $_SESSION['_csrf'] = $t;
    }
    return $t;
}

function csrf_check(?string $token): bool
{
    $t = (string)($_SESSION['_csrf'] ?? '');
    return is_string($token) && $token !== '' && hash_equals($t, $token);
}

function user_id(): ?int
{
    $uid = $_SESSION['user_id'] ?? null;
    if (is_int($uid)) return $uid;
    if (is_numeric($uid)) return (int)$uid;
    return null;
}

function require_login(): int
{
    $uid = user_id();
    if (!$uid) {
        header('Location: ' . url(['action' => 'login']));
        exit;
    }
    return $uid;
}

// ------------------------------------------------------------
// 4) DB Connect + Schema Init
// ------------------------------------------------------------
$dbHost = env('DB_HOST', '127.0.0.1');
$dbName = env('DB_NAME', 'linkledger');
$dbUser = env('DB_USER', 'root');
$dbPass = env('DB_PASS', '');
$dbCharset = env('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
    dbg('DB connect attempt', ['dsn' => $dsn, 'user' => $dbUser]);

    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET sql_mode = 'STRICT_ALL_TABLES'");

    // Auto schema
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL,
        display_name VARCHAR(120) NOT NULL DEFAULT '',
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_users_email (email)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS projects (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(200) NOT NULL,
        description TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_projects_user_name (user_id, name),
        KEY ix_projects_user (user_id),
        CONSTRAINT fk_projects_user FOREIGN KEY (user_id) REFERENCES users(id)
          ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS tags (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(80) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tags_user_name (user_id, name),
        KEY ix_tags_user (user_id),
        CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id)
          ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS links (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        project_id BIGINT UNSIGNED NOT NULL,
        url TEXT NOT NULL,
        canonical_url TEXT NOT NULL,
        canonical_hash CHAR(64) NOT NULL,
        title VARCHAR(500) NULL,
        description TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_links_dup (user_id, project_id, canonical_hash),
        KEY ix_links_user (user_id),
        KEY ix_links_project (project_id),
        CONSTRAINT fk_links_user FOREIGN KEY (user_id) REFERENCES users(id)
          ON DELETE CASCADE,
        CONSTRAINT fk_links_project FOREIGN KEY (project_id) REFERENCES projects(id)
          ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS link_tags (
        link_id BIGINT UNSIGNED NOT NULL,
        tag_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (link_id, tag_id),
        CONSTRAINT fk_link_tags_link FOREIGN KEY (link_id) REFERENCES links(id)
          ON DELETE CASCADE,
        CONSTRAINT fk_link_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id)
          ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    dbg('DB schema init done');

/**
 * Prüft, ob eine Spalte in einer Tabelle existiert (für "Schema-Mismatch" auf Shared Hosting).
 * Hintergrund: CREATE TABLE IF NOT EXISTS ändert bestehende Tabellen NICHT.
 *
 * @param PDO $pdo
 * @param string $table
 * @param string $column
 * @return bool
 */
function db_has_column(PDO $pdo, string $table, string $column): bool
{
    $sql = "
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :t
        AND COLUMN_NAME = :c
      LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':t' => $table, ':c' => $column]);
    return (bool)$st->fetchColumn();
}
} catch (Throwable $e) {
    dbg('DB exception', ['type' => get_class($e), 'message' => $e->getMessage()]);
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    if ($APP_DEBUG) {
        echo "<h1>DB Fehler</h1>";
        echo "<p><b>request_id:</b> " . htmlspecialchars($requestId) . "</p>";
        echo "<pre>" . htmlspecialchars((string)$e) . "</pre>";
    } else {
        echo "Internal Server Error";
    }
    exit;
}

// ------------------------------------------------------------
// 5) Helpers: URL Validation + Canonicalization + Duplicate Hash
// ------------------------------------------------------------

/**
 * @return array{ok:bool,url:string,error:?string}
 */
function validate_url(string $input): array
{
    $url = trim($input);
    if ($url === '') {
        dbg('URL validation failed: empty');
        return ['ok' => false, 'url' => $input, 'error' => 'URL ist leer.'];
    }

    // Scheme ergänzen
    if (!preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://#', $url)) {
        dbg('URL scheme missing -> prepend https://', ['input' => $url]);
        $url = 'https://' . $url;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        dbg('URL validation failed: FILTER_VALIDATE_URL', ['url' => $url]);
        return ['ok' => false, 'url' => $input, 'error' => 'URL ist ungültig.'];
    }

    $p = parse_url($url);
    if (!$p || empty($p['scheme']) || empty($p['host'])) {
        dbg('URL validation failed: missing scheme/host', ['url' => $url]);
        return ['ok' => false, 'url' => $input, 'error' => 'URL muss Scheme und Host enthalten.'];
    }

    $scheme = strtolower((string)$p['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        dbg('URL validation failed: scheme not allowed', ['scheme' => $scheme]);
        return ['ok' => false, 'url' => $input, 'error' => 'Nur http/https sind erlaubt.'];
    }

    if (preg_match('/\s/', (string)$p['host'])) {
        dbg('URL validation failed: whitespace in host', ['host' => $p['host']]);
        return ['ok' => false, 'url' => $input, 'error' => 'Host enthält ungültige Zeichen.'];
    }

    dbg('URL validation ok', ['normalized' => $url]);
    return ['ok' => true, 'url' => $url, 'error' => null];
}

function canonicalize_url(string $url): string
{
    $p = parse_url($url);
    if (!$p || empty($p['host'])) return $url;

    $scheme = strtolower((string)($p['scheme'] ?? 'https'));
    $host = strtolower((string)$p['host']);
    $port = $p['port'] ?? null;

    if (($scheme === 'http' && $port == 80) || ($scheme === 'https' && $port == 443)) {
        $port = null;
    }

    $path = (string)($p['path'] ?? '');
    if ($path !== '/') $path = rtrim($path, '/');

    $query = isset($p['query']) ? '?' . $p['query'] : '';
    $portPart = $port ? ':' . (int)$port : '';

    $canon = $scheme . '://' . $host . $portPart . $path . $query;
    dbg('URL canonicalized', ['canonical' => $canon]);
    return $canon;
}

function sha256(string $text): string
{
    return hash('sha256', $text);
}

// ------------------------------------------------------------
// 6) Actions (CGI-like branching)
// ------------------------------------------------------------
$action = (string)($_REQUEST['action'] ?? 'app');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
dbg('Request', ['method' => $method, 'action' => $action]);

// CSRF for all POST
if ($method === 'POST') {
    $token = $_POST['_csrf'] ?? null;
    if (!csrf_check(is_string($token) ? $token : null)) {
        flash('error', 'CSRF Token ungültig. Bitte Seite neu laden.');
        header('Location: ' . url(['action' => 'app']));
        exit;
    }
}

// Auth actions
if ($action === 'logout') {
    unset($_SESSION['user_id']);
    flash('success', 'Du bist ausgeloggt.');
    header('Location: ' . url(['action' => 'login']));
    exit;
}

if ($action === 'login_post' && $method === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
    $stmt->execute([':e' => $email]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($pass, (string)$u['password_hash'])) {
        flash('error', 'Login fehlgeschlagen.');
        header('Location: ' . url(['action' => 'login']));
        exit;
    }

    $_SESSION['user_id'] = (int)$u['id'];
    flash('success', 'Willkommen!');
    header('Location: ' . url(['action' => 'app']));
    exit;
}

if ($action === 'register_post' && $method === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        flash('warn', 'Bitte E-Mail und Passwort ausfüllen.');
        header('Location: ' . url(['action' => 'register']));
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
    $stmt->execute([':e' => $email]);
    if ($stmt->fetch()) {
        flash('warn', 'E-Mail ist bereits registriert.');
        header('Location: ' . url(['action' => 'register']));
        exit;
    }

    $displayName = trim((string)($_POST['display_name'] ?? ''));

    // Fallback: aus E-Mail ableiten (Teil vor dem @), wenn leer
    if ($displayName === '' && str_contains($email, '@')) {
        $displayName = substr($email, 0, (int)strpos($email, '@'));
    }
    // Limit (DB-Spalte ist 120)
    $displayName = mb_substr($displayName, 0, 120);

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // IMPORTANT:
    // Deine Datenbank scheint bereits eine Spalte `display_name` zu haben, die NOT NULL ist.
    // Falls die Spalte existiert, müssen wir sie beim INSERT befüllen.
    if (function_exists('db_has_column') && db_has_column($pdo, 'users', 'display_name')) {
        $stmt = $pdo->prepare("INSERT INTO users (email, display_name, password_hash) VALUES (:e, :dn, :h)");
        $stmt->execute([':e' => $email, ':dn' => $displayName, ':h' => $hash]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (:e, :h)");
        $stmt->execute([':e' => $email, ':h' => $hash]);
    }

    flash('success', 'Registriert! Bitte einloggen.');
    header('Location: ' . url(['action' => 'login']));
    exit;
}

// Export actions
if ($action === 'export_json') {
    $uid = require_login();
    $projectId = (int)($_GET['project_id'] ?? 0);

    $projects = $pdo->prepare("SELECT * FROM projects WHERE user_id = :u ORDER BY name");
    $projects->execute([':u' => $uid]);
    $projects = $projects->fetchAll();

    $tags = $pdo->prepare("SELECT * FROM tags WHERE user_id = :u ORDER BY name");
    $tags->execute([':u' => $uid]);
    $tags = $tags->fetchAll();

    $where = "WHERE l.user_id = :u";
    $params = [':u' => $uid];
    if ($projectId > 0) {
        $where .= " AND l.project_id = :p";
        $params[':p'] = $projectId;
    }

    $linksStmt = $pdo->prepare("SELECT l.* FROM links l $where ORDER BY l.updated_at DESC");
    $linksStmt->execute($params);
    $links = $linksStmt->fetchAll();

    foreach ($links as &$l) {
        $lt = $pdo->prepare("
          SELECT t.id, t.name
          FROM tags t
          JOIN link_tags lt ON lt.tag_id = t.id
          WHERE lt.link_id = :lid
          ORDER BY t.name
        ");
        $lt->execute([':lid' => (int)$l['id']]);
        $l['tags'] = $lt->fetchAll();
    }
    unset($l);

    $snapshot = [
        'schema_version' => 1,
        'generated_at' => gmdate('c'),
        'projects' => $projects,
        'tags' => $tags,
        'links' => $links,
    ];

    $file = 'linkledger_export_' . gmdate('Y-m-d') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'export_csv') {
    $uid = require_login();
    $projectId = (int)($_GET['project_id'] ?? 0);

    $projectsStmt = $pdo->prepare("SELECT id, name FROM projects WHERE user_id = :u");
    $projectsStmt->execute([':u' => $uid]);
    $projects = $projectsStmt->fetchAll();

    $projectNameById = [];
    foreach ($projects as $p) $projectNameById[(int)$p['id']] = (string)$p['name'];

    $where = "WHERE l.user_id = :u";
    $params = [':u' => $uid];
    if ($projectId > 0) {
        $where .= " AND l.project_id = :p";
        $params[':p'] = $projectId;
    }

    $linksStmt = $pdo->prepare("SELECT l.* FROM links l $where ORDER BY l.updated_at DESC");
    $linksStmt->execute($params);
    $links = $linksStmt->fetchAll();

    $rows = [];
    $rows[] = "project;url;title;description;tags";

    $csv = function (string $value): string {
        if (strpbrk($value, ";\n\r\"") === false) return $value;
        return '"' . str_replace('"', '""', $value) . '"';
    };

    foreach ($links as $l) {
        $tagStmt = $pdo->prepare("
          SELECT t.name
          FROM tags t
          JOIN link_tags lt ON lt.tag_id = t.id
          WHERE lt.link_id = :lid
          ORDER BY t.name
        ");
        $tagStmt->execute([':lid' => (int)$l['id']]);
        $tagNames = array_map(fn($r) => (string)$r['name'], $tagStmt->fetchAll());

        $rows[] = implode(';', [
            $csv($projectNameById[(int)$l['project_id']] ?? ''),
            $csv((string)$l['url']),
            $csv((string)($l['title'] ?? '')),
            $csv((string)($l['description'] ?? '')),
            $csv(implode(',', $tagNames)),
        ]);
    }

    $file = $projectId > 0 ? ('links_project_' . $projectId . '_' . gmdate('Y-m-d') . '.csv') : ('links_' . gmdate('Y-m-d') . '.csv');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    echo "\xEF\xBB\xBF";
    echo implode("\r\n", $rows);
    exit;
}

// Mutating actions require login
$uid = null;
if (!in_array($action, ['login', 'register', 'login_post', 'register_post'], true)) {
    $uid = require_login();
}

function redirect_app(int $projectId = 0, int $linkId = 0, string $q = '', int $tagId = 0, bool $new = false): void
{
    $params = ['action' => 'app'];
    if ($projectId > 0) $params['project_id'] = $projectId;
    if ($linkId > 0) $params['link_id'] = $linkId;
    if ($q !== '') $params['q'] = $q;
    if ($tagId > 0) $params['tag_id'] = $tagId;
    if ($new) $params['new'] = 1;

    header('Location: ' . url($params));
    exit;
}

if ($action === 'project_create' && $method === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));

    // Form-Werte für Redirect merken (damit "Neu" nicht aus Versehen überschreibt)
    flash_data('link_form', [
        'url' => $urlInput,
        'title' => $title,
        'description' => $desc,
    ]);
    if ($name === '') { flash('warn', 'Projektname fehlt.'); redirect_app(); }

    try {
        $stmt = $pdo->prepare("INSERT INTO projects (user_id, name, description) VALUES (:u, :n, :d)");
        $stmt->execute([':u' => $uid, ':n' => $name, ':d' => ($desc !== '' ? $desc : null)]);
        flash('success', 'Projekt angelegt.');
        redirect_app((int)$pdo->lastInsertId(), 0);
    } catch (PDOException $e) {
        dbg('project_create exception', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);
        if ($e->getCode() === '23000') flash('warn', 'Projektname existiert bereits.');
        else flash('error', 'DB Fehler beim Anlegen des Projekts.');
        redirect_app();
    }
}

if ($action === 'project_delete' && $method === 'POST') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    if ($projectId <= 0) { flash('warn', 'Kein Projekt ausgewählt.'); redirect_app(); }
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = :p AND user_id = :u");
    $stmt->execute([':p' => $projectId, ':u' => $uid]);
    flash('success', 'Projekt gelöscht.');
    redirect_app();
}

if ($action === 'link_save' && $method === 'POST') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $linkId = (int)($_POST['link_id'] ?? 0);
    $urlInput = (string)($_POST['url'] ?? '');
    $title = trim((string)($_POST['title'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));

    if ($projectId <= 0) { flash('error', 'Kein Projekt ausgewählt.'); redirect_app(); }

    $val = validate_url($urlInput);
    if (!$val['ok']) { flash('warn', 'URL ungültig: ' . ($val['error'] ?? '')); redirect_app($projectId, $linkId, '', 0, $linkId <= 0); }

    $urlOk = $val['url'];
    $canon = canonicalize_url($urlOk);
    $hash = sha256($canon);

    try {
        if ($linkId > 0) {
            $stmt = $pdo->prepare("
              UPDATE links
              SET url=:url, canonical_url=:c, canonical_hash=:h, title=:t, description=:d
              WHERE id=:id AND user_id=:u
            ");
            $stmt->execute([
                ':url' => $urlOk,
                ':c' => $canon,
                ':h' => $hash,
                ':t' => ($title !== '' ? $title : null),
                ':d' => ($desc !== '' ? $desc : null),
                ':id' => $linkId,
                ':u' => $uid,
            ]);
            flash('success', 'Link gespeichert.');
            flash_data('link_form', []);
            redirect_app($projectId, $linkId);
}

        $stmt = $pdo->prepare("
          INSERT INTO links (user_id, project_id, url, canonical_url, canonical_hash, title, description)
          VALUES (:u,:p,:url,:c,:h,:t,:d)
        ");
        $stmt->execute([
            ':u' => $uid,
            ':p' => $projectId,
            ':url' => $urlOk,
            ':c' => $canon,
            ':h' => $hash,
            ':t' => ($title !== '' ? $title : null),
            ':d' => ($desc !== '' ? $desc : null),
        ]);
        flash('success', 'Link gespeichert.');
        flash_data('link_form', []);
        redirect_app($projectId, (int)$pdo->lastInsertId());
} catch (PDOException $e) {
        dbg('link_save exception', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);
        if ($e->getCode() === '23000') flash('warn', 'Diese URL existiert im Projekt bereits.');
        else flash('error', 'DB Fehler beim Speichern des Links.');
        redirect_app($projectId, $linkId, '', 0, $linkId <= 0);
    }
}

if ($action === 'link_delete' && $method === 'POST') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $linkId = (int)($_POST['link_id'] ?? 0);
    if ($linkId <= 0) { flash('warn', 'Kein Link ausgewählt.'); redirect_app($projectId); }
    $stmt = $pdo->prepare("DELETE FROM links WHERE id = :id AND user_id = :u");
    $stmt->execute([':id' => $linkId, ':u' => $uid]);
    flash('success', 'Link gelöscht.');
    redirect_app($projectId);
}

if ($action === 'tag_create' && $method === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $projectId = (int)($_POST['project_id'] ?? 0);
    $linkId = (int)($_POST['link_id'] ?? 0);
    if ($name === '') { flash('warn', 'Tag-Name fehlt.'); redirect_app($projectId, $linkId, '', 0, $linkId <= 0); }

    try {
        $stmt = $pdo->prepare("INSERT INTO tags (user_id, name) VALUES (:u, :n)");
        $stmt->execute([':u' => $uid, ':n' => $name]);
        flash('success', 'Tag angelegt.');
    } catch (PDOException $e) {
        dbg('tag_create exception', ['code' => $e->getCode(), 'msg' => $e->getMessage()]);
        if ($e->getCode() === '23000') flash('warn', 'Tag existiert bereits.');
        else flash('error', 'DB Fehler beim Anlegen des Tags.');
    }
    redirect_app($projectId, $linkId, '', 0, $linkId <= 0);
}

if ($action === 'tag_delete' && $method === 'POST') {
    $tagId = (int)($_POST['tag_id'] ?? 0);
    $projectId = (int)($_POST['project_id'] ?? 0);
    $linkId = (int)($_POST['link_id'] ?? 0);
    if ($tagId <= 0) { flash('warn', 'Kein Tag ausgewählt.'); redirect_app($projectId, $linkId, '', 0, $linkId <= 0); }

    $stmt = $pdo->prepare("DELETE FROM tags WHERE id = :t AND user_id = :u");
    $stmt->execute([':t' => $tagId, ':u' => $uid]);
    flash('success', 'Tag gelöscht.');
    redirect_app($projectId, $linkId, '', 0, $linkId <= 0);
}

if ($action === 'link_tag_add' && $method === 'POST') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $linkId = (int)($_POST['link_id'] ?? 0);
    $tagId = (int)($_POST['tag_id'] ?? 0);
    if ($linkId <= 0 || $tagId <= 0) { flash('warn', 'Bitte Link und Tag auswählen.'); redirect_app($projectId, $linkId, '', 0, $linkId <= 0); }

    $st = $pdo->prepare("SELECT id FROM tags WHERE id = :t AND user_id = :u LIMIT 1");
    $st->execute([':t' => $tagId, ':u' => $uid]);
    if (!$st->fetch()) { flash('warn', 'Tag nicht gefunden.'); redirect_app($projectId, $linkId, '', 0, $linkId <= 0); }

    $stmt = $pdo->prepare("INSERT IGNORE INTO link_tags (link_id, tag_id) VALUES (:l, :t)");
    $stmt->execute([':l' => $linkId, ':t' => $tagId]);
    flash('success', 'Tag zugewiesen.');
    redirect_app($projectId, $linkId, '', 0, $linkId <= 0);
}

if ($action === 'link_tag_remove' && $method === 'POST') {
    $projectId = (int)($_POST['project_id'] ?? 0);
    $linkId = (int)($_POST['link_id'] ?? 0);
    $tagId = (int)($_POST['tag_id'] ?? 0);
    if ($linkId <= 0 || $tagId <= 0) { flash('warn', 'Ungültige Parameter.'); redirect_app($projectId, $linkId, '', 0, $linkId <= 0); }

    $stmt = $pdo->prepare("DELETE FROM link_tags WHERE link_id = :l AND tag_id = :t");
    $stmt->execute([':l' => $linkId, ':t' => $tagId]);
    flash('success', 'Tag entfernt.');
    redirect_app($projectId, $linkId, '', 0, $linkId <= 0);
}

// ------------------------------------------------------------
// 7) HTML Rendering
// ------------------------------------------------------------
$flash = consume_flash();
$csrf = csrf_token();

function html_header(string $title = 'LinkLedger'): void
{
    echo "<!doctype html><html lang='de'><head>";
    echo "<meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title) . "</title>";
    echo "<script src='https://cdn.tailwindcss.com'></script>";
    echo "</head><body class='bg-slate-100 text-slate-900'>";
}

function html_footer(): void
{
    global $requestId;
    echo "<footer class='p-4 text-xs text-slate-500'>request_id: " . htmlspecialchars($requestId) . "</footer>";
    echo render_console_logs();
    echo "</body></html>";
}

// Login / Register pages
if ($action === 'login') {
    html_header('Login – LinkLedger');
    echo "<div class='bg-white border-b border-slate-200'><div class='max-w-4xl mx-auto px-4 py-3 flex justify-between'>";
    echo "<div class='font-semibold'>LinkLedger</div>";
    echo "<div class='text-sm'><a class='hover:underline' href='" . htmlspecialchars(url(['action' => 'register'])) . "'>Register</a></div>";
    echo "</div></div>";

    if (!empty($flash)) {
        echo "<div class='max-w-4xl mx-auto px-4 pt-4 space-y-2'>";
        foreach ($flash as $m) {
            $type = $m['type'] ?? 'info';
            $cls = "bg-slate-100 text-slate-900 border-slate-200";
            if ($type === 'success') $cls = "bg-green-100 text-green-900 border-green-200";
            if ($type === 'warn') $cls = "bg-yellow-100 text-yellow-900 border-yellow-200";
            if ($type === 'error') $cls = "bg-red-100 text-red-900 border-red-200";
            echo "<div class='border rounded-xl p-3 $cls'>" . htmlspecialchars((string)$m['message']) . "</div>";
        }
        echo "</div>";
    }

    echo "<div class='max-w-md mx-auto bg-white rounded-2xl shadow-sm p-6 mt-8'>";
    echo "<h1 class='text-xl font-semibold'>Login</h1>";
    echo "<form class='mt-4 space-y-3' method='post' action='" . htmlspecialchars(url(['action' => 'login_post'])) . "'>";
    echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
    echo "<div><label class='block text-sm'>E-Mail</label><input class='w-full border rounded-xl p-2' name='email' type='email' required></div>";
    echo "<div><label class='block text-sm'>Anzeigename</label><input class='w-full border rounded-xl p-2' name='display_name' type='text' placeholder='z.B. Robin' required></div>";
    echo "<div><label class='block text-sm'>Passwort</label><input class='w-full border rounded-xl p-2' name='password' type='password' required></div>";
    echo "<button class='bg-slate-900 text-white rounded-xl px-4 py-2'>Login</button>";
    echo "</form></div>";
    html_footer();
    exit;
}

if ($action === 'register') {
    html_header('Register – LinkLedger');
    echo "<div class='bg-white border-b border-slate-200'><div class='max-w-4xl mx-auto px-4 py-3 flex justify-between'>";
    echo "<div class='font-semibold'>LinkLedger</div>";
    echo "<div class='text-sm'><a class='hover:underline' href='" . htmlspecialchars(url(['action' => 'login'])) . "'>Login</a></div>";
    echo "</div></div>";

    if (!empty($flash)) {
        echo "<div class='max-w-4xl mx-auto px-4 pt-4 space-y-2'>";
        foreach ($flash as $m) {
            $type = $m['type'] ?? 'info';
            $cls = "bg-slate-100 text-slate-900 border-slate-200";
            if ($type === 'success') $cls = "bg-green-100 text-green-900 border-green-200";
            if ($type === 'warn') $cls = "bg-yellow-100 text-yellow-900 border-yellow-200";
            if ($type === 'error') $cls = "bg-red-100 text-red-900 border-red-200";
            echo "<div class='border rounded-xl p-3 $cls'>" . htmlspecialchars((string)$m['message']) . "</div>";
        }
        echo "</div>";
    }

    echo "<div class='max-w-md mx-auto bg-white rounded-2xl shadow-sm p-6 mt-8'>";
    echo "<h1 class='text-xl font-semibold'>Register</h1>";
    echo "<form class='mt-4 space-y-3' method='post' action='" . htmlspecialchars(url(['action' => 'register_post'])) . "'>";
    echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
    echo "<div><label class='block text-sm'>E-Mail</label><input class='w-full border rounded-xl p-2' name='email' type='email' required></div>";
    echo "<div><label class='block text-sm'>Anzeigename</label><input class='w-full border rounded-xl p-2' name='display_name' type='text' placeholder='z.B. Robin' required></div>";
    echo "<div><label class='block text-sm'>Passwort</label><input class='w-full border rounded-xl p-2' name='password' type='password' required></div>";
    echo "<button class='bg-slate-900 text-white rounded-xl px-4 py-2'>Konto anlegen</button>";
    echo "</form></div>";
    html_footer();
    exit;
}

// ---------------- App screen ----------------
$newMode = ((int)($_GET['new'] ?? 0) === 1);
$oldForm = consume_flash_data('link_form', []);
if (!is_array($oldForm)) $oldForm = [];

$projectId = (int)($_GET['project_id'] ?? 0);
$linkId = (int)($_GET['link_id'] ?? 0);
$q = trim((string)($_GET['q'] ?? ''));
$tagId = (int)($_GET['tag_id'] ?? 0);

// projects
$projectsStmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = :u ORDER BY name ASC");
$projectsStmt->execute([':u' => $uid]);
$projects = $projectsStmt->fetchAll();

if (empty($projects)) {
    try {
        $pdo->prepare("INSERT INTO projects (user_id, name, description) VALUES (:u, 'Inbox', 'Standard-Projekt (Auto)')")
            ->execute([':u' => $uid]);
    } catch (Throwable $e) {
        dbg('seed inbox failed', ['message' => $e->getMessage()]);
    }
    $projectsStmt->execute([':u' => $uid]);
    $projects = $projectsStmt->fetchAll();
}

$selectedProject = null;
if ($projectId > 0) {
    foreach ($projects as $p) if ((int)$p['id'] === $projectId) { $selectedProject = $p; break; }
}
if (!$selectedProject && !empty($projects)) { $selectedProject = $projects[0]; $projectId = (int)$selectedProject['id']; }

// tags
$tagsStmt = $pdo->prepare("SELECT * FROM tags WHERE user_id = :u ORDER BY name ASC");
$tagsStmt->execute([':u' => $uid]);
$tags = $tagsStmt->fetchAll();

// links with filter
$links = [];
if ($selectedProject) {
    $where = ["l.user_id = :u", "l.project_id = :p"];
    $params = [':u' => $uid, ':p' => $projectId];
    $join = '';

    if ($tagId > 0) {
        $join .= " JOIN link_tags lt ON lt.link_id = l.id ";
        $where[] = "lt.tag_id = :tag";
        $params[':tag'] = $tagId;
    }
    if ($q !== '') {
        $where[] = "(l.url LIKE :q OR l.title LIKE :q OR l.description LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    $sql = "SELECT DISTINCT l.* FROM links l $join WHERE " . implode(' AND ', $where) . " ORDER BY l.updated_at DESC, l.created_at DESC";
    $linksStmt = $pdo->prepare($sql);
    $linksStmt->execute($params);
    $links = $linksStmt->fetchAll();
}

// select link
$selectedLink = null;
if ($linkId > 0) {
    foreach ($links as $l) if ((int)$l['id'] === $linkId) { $selectedLink = $l; break; }
}
if (!$newMode && !$selectedLink && !empty($links)) { $selectedLink = $links[0]; $linkId = (int)$selectedLink['id']; }

// tags for selected link
$selectedLinkTags = [];
if ($selectedLink) {
    $lt = $pdo->prepare("
      SELECT t.*
      FROM tags t
      JOIN link_tags lt ON lt.tag_id = t.id
      WHERE lt.link_id = :lid
      ORDER BY t.name ASC
    ");
    $lt->execute([':lid' => (int)$selectedLink['id']]);
    $selectedLinkTags = $lt->fetchAll();
}

html_header('LinkLedger');

// top bar
echo "<div class='bg-white border-b border-slate-200'>";
echo "  <div class='max-w-7xl mx-auto px-4 py-2 flex items-center justify-between'>";
echo "    <div class='font-semibold'>LinkLedger</div>";
echo "    <div class='text-sm text-slate-600'>Eingeloggt <a class='ml-3 text-slate-700 hover:underline' href='" . htmlspecialchars(url(['action' => 'logout'])) . "'>Logout</a></div>";
echo "  </div>";
echo "</div>";

// flash
if (!empty($flash)) {
    echo "<div class='max-w-7xl mx-auto px-4 pt-4 space-y-2'>";
    foreach ($flash as $m) {
        $type = $m['type'] ?? 'info';
        $cls = "bg-slate-100 text-slate-900 border-slate-200";
        if ($type === 'success') $cls = "bg-green-100 text-green-900 border-green-200";
        if ($type === 'warn') $cls = "bg-yellow-100 text-yellow-900 border-yellow-200";
        if ($type === 'error') $cls = "bg-red-100 text-red-900 border-red-200";
        echo "<div class='border rounded-xl p-3 $cls'>" . htmlspecialchars((string)$m['message']) . "</div>";
    }
    echo "</div>";
}

echo "<main class='p-4'><div class='max-w-7xl mx-auto'><div class='grid grid-cols-12 gap-4' style='min-height:70vh;'>";

// left projects
echo "<aside class='col-span-12 md:col-span-3 bg-slate-800 text-white rounded-2xl p-4'>";
echo "<h2 class='text-lg font-semibold mb-3'>Projekte</h2>";

echo "<form method='post' action='" . htmlspecialchars(url(['action' => 'project_create'])) . "' class='space-y-2 mb-4'>";
echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
echo "<input type='text' name='name' placeholder='Neues Projekt...' class='w-full rounded-xl p-2 text-slate-900' required>";
echo "<input type='text' name='description' placeholder='Beschreibung (optional)' class='w-full rounded-xl p-2 text-slate-900'>";
echo "<button class='w-full bg-white/10 hover:bg-white/20 rounded-xl py-2 text-sm'>+ Projekt anlegen</button>";
echo "</form>";

echo "<form method='post' action='" . htmlspecialchars(url(['action' => 'project_delete'])) . "' class='mb-4'>";
echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
echo "<input type='hidden' name='project_id' value='" . (int)$projectId . "'>";
echo "<button class='w-full bg-red-500/20 hover:bg-red-500/30 rounded-xl py-2 text-sm'>Projekt löschen</button>";
echo "</form>";

echo "<nav class='space-y-1'>";
foreach ($projects as $p) {
    $active = ((int)$p['id'] === $projectId);
    $cls = $active ? "bg-sky-500/30" : "hover:bg-white/10";
    $href = url(['action' => 'app', 'project_id' => (int)$p['id'], 'link_id' => 0, 'q' => $q, 'tag_id' => $tagId]);
    echo "<a class='block rounded-xl px-3 py-2 $cls' href='" . htmlspecialchars($href) . "'>" . htmlspecialchars((string)$p['name']) . "</a>";
}
echo "</nav></aside>";

// middle links list
echo "<section class='col-span-12 md:col-span-5 bg-white rounded-2xl p-4 border'>";
echo "<div class='mb-3'><form class='flex gap-2' method='get' action='" . htmlspecialchars(url()) . "'>";
echo "<input type='hidden' name='action' value='app'>";
echo "<input type='hidden' name='new' value='0'>";
echo "<input type='hidden' name='project_id' value='" . (int)$projectId . "'>";
echo "<input type='hidden' name='link_id' value='" . (int)$linkId . "'>";
echo "<input type='text' name='q' value='" . htmlspecialchars($q) . "' placeholder='Suche...' class='flex-1 border rounded-xl p-2'>";
echo "<select name='tag_id' class='border rounded-xl p-2'><option value='0'>Tag-Filter</option>";
foreach ($tags as $t) {
    $sel = ((int)$t['id'] === $tagId) ? "selected" : "";
    echo "<option value='" . (int)$t['id'] . "' $sel>" . htmlspecialchars((string)$t['name']) . "</option>";
}
echo "</select><button class='border rounded-xl px-3'>Filter</button></form></div>";

echo "<div class='mb-3 flex flex-wrap gap-2'>";
echo "<a class='px-3 py-2 rounded-xl bg-slate-900 text-white text-sm' href='" . htmlspecialchars(url(['action' => 'app', 'project_id' => $projectId, 'link_id' => 0, 'new' => 1, 'q' => $q, 'tag_id' => $tagId])) . "'>Neu</a>";
echo "<a class='px-3 py-2 rounded-xl border text-sm' href='" . htmlspecialchars(url(['action' => 'export_csv', 'project_id' => $projectId])) . "'>Export CSV</a>";
echo "<a class='px-3 py-2 rounded-xl border text-sm' href='" . htmlspecialchars(url(['action' => 'export_json', 'project_id' => $projectId])) . "'>Export JSON</a>";
echo "</div>";

echo "<div class='divide-y'>";
if (empty($links)) echo "<div class='text-slate-600 py-4'>Keine Links gefunden.</div>";
foreach ($links as $l) {
    $isActive = ((int)$l['id'] === $linkId);
    $wrapCls = $isActive ? "bg-slate-50 rounded-xl px-3" : "";
    $href = url(['action' => 'app', 'project_id' => $projectId, 'link_id' => (int)$l['id'], 'q' => $q, 'tag_id' => $tagId]);
    $titleText = (string)($l['title'] ?? '');
    if ($titleText === '') $titleText = '(ohne Titel)';
    echo "<a class='block py-3 $wrapCls' href='" . htmlspecialchars($href) . "'>";
    echo "<div class='font-semibold'>" . htmlspecialchars($titleText) . "</div>";
    echo "<div class='text-sm text-slate-500 break-all'>" . htmlspecialchars((string)$l['url']) . "</div>";
    echo "</a>";
}
echo "</div></section>";

// right details
$formUrl = $newMode ? (string)($oldForm['url'] ?? '') : ($selectedLink ? (string)$selectedLink['url'] : '');
$formTitle = $newMode ? (string)($oldForm['title'] ?? '') : ($selectedLink ? (string)($selectedLink['title'] ?? '') : '');
$formDesc = $newMode ? (string)($oldForm['description'] ?? '') : ($selectedLink ? (string)($selectedLink['description'] ?? '') : '');
$formLinkId = $newMode ? 0 : ($selectedLink ? (int)$selectedLink['id'] : 0);

echo "<section class='col-span-12 md:col-span-4 bg-white rounded-2xl p-4 border'>";
echo "<div class='flex items-center justify-between mb-3'><h2 class='text-lg font-semibold'>" . ($newMode ? 'Neuer Link' : 'Details') . "</h2>";
if ($formUrl !== '') echo "<a class='text-sm text-slate-600 hover:underline' href='" . htmlspecialchars($formUrl) . "' target='_blank'>open ↗</a>";
echo "</div>";

echo "<form method='post' action='" . htmlspecialchars(url(['action' => 'link_save'])) . "' class='space-y-3'>";
echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
echo "<input type='hidden' name='project_id' value='" . (int)$projectId . "'>";
echo "<input type='hidden' name='link_id' value='" . (int)$formLinkId . "'>";
echo "<div><label class='block text-sm font-semibold'>URL</label>";
echo "<input class='w-full border rounded-xl p-2' name='url' value='" . htmlspecialchars($formUrl) . "' placeholder='https://example.com oder example.com'>";
echo "<div class='text-xs text-slate-500 mt-1'>Nur http/https. Scheme wird ergänzt, falls es fehlt.</div></div>";
echo "<div><label class='block text-sm font-semibold'>Titel</label><input class='w-full border rounded-xl p-2' name='title' value='" . htmlspecialchars($formTitle) . "'></div>";
echo "<div><label class='block text-sm font-semibold'>Beschreibung</label><textarea class='w-full border rounded-xl p-2' name='description' rows='4'>" . htmlspecialchars($formDesc) . "</textarea></div>";
echo "<button class='px-4 py-2 rounded-xl bg-slate-900 text-white text-sm'>Speichern</button></form>";

if ($formLinkId > 0) {
    echo "<form method='post' action='" . htmlspecialchars(url(['action' => 'link_delete'])) . "' class='mt-2'>";
    echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
    echo "<input type='hidden' name='project_id' value='" . (int)$projectId . "'>";
    echo "<input type='hidden' name='link_id' value='" . (int)$formLinkId . "'>";
    echo "<button class='px-4 py-2 rounded-xl border text-sm'>Löschen</button></form>";
}

echo "<hr class='my-4'><h3 class='font-semibold mb-2'>Tags</h3>";
if ($formLinkId <= 0) {
    echo "<div class='text-sm text-slate-600'>Wähle zuerst einen Link aus (oder lege einen an), um Tags zuzuweisen.</div>";
} else {
    echo "<form method='post' action='" . htmlspecialchars(url(['action' => 'link_tag_add'])) . "' class='flex gap-2 items-center mb-3'>";
    echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
    echo "<input type='hidden' name='project_id' value='" . (int)$projectId . "'>";
    echo "<input type='hidden' name='link_id' value='" . (int)$formLinkId . "'>";
    echo "<select name='tag_id' class='flex-1 border rounded-xl p-2'><option value='0'>Tag hinzufügen...</option>";
    foreach ($tags as $t) echo "<option value='" . (int)$t['id'] . "'>" . htmlspecialchars((string)$t['name']) . "</option>";
    echo "</select><button class='px-3 py-2 rounded-xl bg-sky-600 text-white text-sm'>Hinzufügen</button></form>";

    echo "<div class='flex flex-wrap gap-2'>";
    foreach ($selectedLinkTags as $t) {
        echo "<form method='post' action='" . htmlspecialchars(url(['action' => 'link_tag_remove'])) . "'>";
        echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
        echo "<input type='hidden' name='project_id' value='" . (int)$projectId . "'>";
        echo "<input type='hidden' name='link_id' value='" . (int)$formLinkId . "'>";
        echo "<input type='hidden' name='tag_id' value='" . (int)$t['id'] . "'>";
        echo "<button class='px-3 py-1 rounded-full bg-slate-200 text-sm'>" . htmlspecialchars((string)$t['name']) . " <span class='ml-1 text-slate-600'>×</span></button>";
        echo "</form>";
    }
    echo "</div>";
}

echo "<hr class='my-4'><h3 class='font-semibold mb-2'>Tag-Verwaltung</h3>";
echo "<form method='post' action='" . htmlspecialchars(url(['action' => 'tag_create'])) . "' class='flex gap-2 mb-2'>";
echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
echo "<input type='hidden' name='project_id' value='" . (int)$projectId . "'>";
echo "<input type='hidden' name='link_id' value='" . (int)$formLinkId . "'>";
echo "<input name='name' class='flex-1 border rounded-xl p-2' placeholder='Neuer Tag...'>";
echo "<button class='px-3 py-2 rounded-xl bg-slate-900 text-white text-sm'>+</button></form>";

echo "<form method='post' action='" . htmlspecialchars(url(['action' => 'tag_delete'])) . "' class='flex gap-2'>";
echo "<input type='hidden' name='_csrf' value='" . htmlspecialchars($csrf) . "'>";
echo "<input type='hidden' name='project_id' value='" . (int)$projectId . "'>";
echo "<input type='hidden' name='link_id' value='" . (int)$formLinkId . "'>";
echo "<select name='tag_id' class='flex-1 border rounded-xl p-2'><option value='0'>Tag löschen...</option>";
foreach ($tags as $t) echo "<option value='" . (int)$t['id'] . "'>" . htmlspecialchars((string)$t['name']) . "</option>";
echo "</select><button class='px-3 py-2 rounded-xl border text-sm'>Löschen</button></form>";

echo "</section></div></div></main>";

html_footer();
