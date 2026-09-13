<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/UserRepository.php';
require __DIR__ . '/../src/RouteRepository.php';

$users  = new UserRepository($pdo);
$routes = new RouteRepository($pdo);

$username = trim((string) ($_GET['u'] ?? ''));

// No ?u means "my own profile" — which needs somebody to be signed in.
if ($username === '') {
    $userId = current_user_id();
    if ($userId === null) {
        redirect('/auth/login.php');
    }

    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $username = (string) $stmt->fetchColumn();
}

$user = $users->findByUsername($username);

if ($user === null) {
    http_response_code(404);
    render('not-found', ['message' => 'No such user.'], 'Routeler — Not found');
    exit;
}

render(
    'profile',
    ['user' => $user, 'routes' => $routes->listByAuthor($user['username'])],
    'Routeler — @' . $user['username']
);
