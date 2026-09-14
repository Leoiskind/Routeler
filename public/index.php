<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/RouteRepository.php';

$repo = new RouteRepository(pdo());
$routes = $repo->listAll();

render('route-list', ['routes' => $routes], 'Routeler - Routes');