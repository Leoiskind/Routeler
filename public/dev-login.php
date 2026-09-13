<?php
declare(strict_types=1);

/**
 * TEMPORARY — DELETE IN PHASE 5.
 *
 * Signs you in as a chosen user id so the create/save flow can be built
 * before OAuth exists. Guarded by APP_ENV, so it does nothing unless
 * APP_ENV=dev is set in .env — which it must never be in production.
 *
 *   /dev-login.php?id=1     sign in as user 1
 *   /dev-login.php?out=1    sign out
 */

require __DIR__ . '/../src/bootstrap.php';

if (getenv('APP_ENV') !== 'dev') {
    http_response_code(404);
    exit('Not found.');
}

if (isset($_GET['out'])) {
    unset($_SESSION['user_id']);
    redirect('/');
}

$id = (int) ($_GET['id'] ?? 1);
if ($id < 1) {
    http_response_code(400);
    exit('Bad id.');
}

$stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = :id');
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();

if ($user === false) {
    http_response_code(404);
    exit('No such user. Seed one first.');
}

$_SESSION['user_id'] = (int) $user['id'];
redirect('/');
