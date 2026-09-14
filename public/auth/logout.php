<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

use App\Auth\Session;

Session::logout();

header('Location: /');
exit;
