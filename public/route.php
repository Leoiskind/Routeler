<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use App\RouteRepository;

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id < 1) {
    http_response_code(400);
    render('not-found', ['message' => 'That route id is not valid.'], 'Routeler — Bad request');
    exit;
}

$route = (new RouteRepository(pdo()))->find($id);

if ($route === null) {
    http_response_code(404);
    render('not-found', ['message' => 'No route with that id.'], 'Routeler — Not found');
    exit;
}

render('route-detail', ['route' => $route], 'Routeler — ' . $route['name']);
