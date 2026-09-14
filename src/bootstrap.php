<?php
declare(strict_types=1);

/**
 * Every entry point in public/ starts with:
 *
 *     require_once __DIR__ . '/../src/bootstrap.php';
 *
 * After that you have a session, pdo(), and the helpers below.
 */

// Composer's autoloader. Pages require this one file and get every
// class under App\ without a require of their own.
require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * The database connection.
 *
 * A function rather than a $pdo variable left lying around by this file.
 * A variable created here and used in public/index.php is invisible
 * coupling: nothing in that page says where it came from, and static
 * analysis cannot follow a require to find out. A declared function with
 * a declared return type is checkable.
 *
 * static: the connection is created on first call and reused for the rest
 * of the request, so calling pdo() five times opens one connection, not
 * five. It is also lazy — a page that never calls it never connects,
 * which matters when the database is a free tier that sleeps.
 */
function pdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // db.php returns the PDO instance.
        $pdo = require __DIR__ . '/../config/db.php';
    }

    return $pdo;
}

/**
 * Escape a value for output inside HTML.
 *
 * Use this on EVERY variable that reaches a page. Route names and bios are
 * user input; without escaping, someone can name a route
 * <script>...</script> and it runs in every visitor's browser.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Render a view file from src/views/ and return it as a string.
 *
 * $data keys become local variables inside the view, so
 * view('route-card', ['route' => $r]) makes $route available there.
 */
function view(string $name, array $data = []): string
{
    $file = __DIR__ . '/views/' . $name . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("View not found: {$name}");
    }

    extract($data, EXTR_SKIP);

    ob_start();
    require $file;
    return (string) ob_get_clean();
}

/**
 * Render a view inside the site layout and send it to the browser.
 */
function render(string $name, array $data = [], string $title = 'Routeler'): void
{
    $content = view($name, $data);
    echo view('layout', ['content' => $content, 'title' => $title]);
}

/**
 * Send a redirect and stop.
 *
 * The exit matters: header() only queues the header, it does not end the
 * script, so without it the rest of the page would still run.
 */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * Send a JSON response and stop. For the endpoints in public/api/.
 */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * The logged-in user's id, or null.
 *
 * Phase 5 replaces this with Session::currentUserId() once OAuth exists.
 * Until then pages can call it and get null.
 */
function current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    return is_int($id) ? $id : null;
}

/**
 * The public Mapbox token, from the environment.
 */
function mapbox_token(): string
{
    return (string) (getenv('MAPBOX_TOKEN') ?: '');
}

/**
 * The session's CSRF token, created on first use.
 *
 * Cross-site request forgery: another site can make your browser POST to
 * this app while you are signed in — your cookies go along automatically,
 * so the request looks authentic. The defence is a secret the attacker
 * cannot read: a random token kept in the session and echoed in every
 * form. They can make your browser send a request; they cannot know what
 * to put in this field.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

/**
 * The hidden input to drop inside every state-changing form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * True when the submitted token matches the session's.
 *
 * hash_equals() rather than === : it compares in constant time, so the
 * duration of the comparison cannot leak how much of the token was right.
 */
function csrf_valid(): bool
{
    $sent = $_POST['_token'] ?? '';
    return is_string($sent)
        && $sent !== ''
        && hash_equals(csrf_token(), $sent);
}
