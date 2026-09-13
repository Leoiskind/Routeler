<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/Auth/providers.php';
require __DIR__ . '/../../src/UserRepository.php';

function fail(string $message): never
{
    http_response_code(400);
    render('not-found', ['message' => $message], 'Routeler - Sign in');
    exit;
}

if(isset($_GET['error'])){
    redirect('/auth/login.php');
}

if (!Session::consumeState($_GET['state'] ?? null)){
    fail('That sign-in link has expired. Please try again.');
}

$verifier = Session::codeVerifier();
$name = Session::pendingProvider();

if ($verifier === null || $name === null){
    fail('Your sign-in session expired. Please try again.');
}

$provider = auth_provider($name);

if ($provider === null){
    fail('Your sign-in session expired. Please try again.');
}

$code = (string) ($_GET['code'] ?? '');

if ($code === ''){
    fail('No authorization code was returned.');
}

try{
    $identity = $provider->exchange($code, $verifier);
} catch (Throwable $e){
    error_log('OAuth exchange failed: ' . $e->getMessage());
    fail('Sign-in failed. Please try again.');
}

$users = new UserRepository($pdo);

$row = $users->findByProvider($identity->provider, $identity->uid);
$userId = $row !== null ? $row['id'] : $users->upsert($identity);

Session::login($userId);
redirect('/');