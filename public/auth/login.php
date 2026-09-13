<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/Auth/providers.php';

// Already signed in? Nothing to do here.
if (current_user_id() !== null) {
    redirect('/');
}

$configured = auth_providers_configured();
$requested  = (string) ($_GET['with'] ?? '');

// No provider named: show the buttons.
if ($requested === '') {
    render('login', ['providers' => $configured], 'Routeler — Sign in');
    exit;
}

$provider = $configured[$requested] ?? null;

if ($provider === null) {
    http_response_code(400);
    render('not-found', ['message' => 'That sign-in method is not available.'], 'Routeler — Sign in');
    exit;
}

[$state, $challenge] = Session::beginLogin($provider->name());

redirect($provider->authUrl($state, $challenge));
