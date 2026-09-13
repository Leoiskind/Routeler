<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/Auth/providers.php';

Session::logout();

header('Location: /');
exit;
