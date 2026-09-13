<?php
declare(strict_types=1);

/**
 * Every entry point in public/ starts with:
 *
 *     require_once __DIR__ . '/../src/bootstrap.php';
 *
 * After that you have $pdo, a session, and the helpers below.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// config/db.php creates $conn. Aliased to $pdo so the rest of the app
// uses one name for it.
require_once __DIR__ . '/../config/db.php';
$pdo = $conn;

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
