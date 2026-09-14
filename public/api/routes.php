<?php
declare(strict_types=1);

/**
 * POST /api/routes.php
 *
 * Body (JSON):
 *   {
 *     "name":        "Oxford Road Corridor",
 *     "description": "Uni to Piccadilly",     // optional
 *     "distance_m":  2400,                    // optional, integer metres
 *     "points":      [[lng, lat], [lng, lat], ...]
 *   }
 *
 * Returns: {"id": 7} on success, {"error": "..."} otherwise.
 *
 * This file does no SQL. Its whole job is: check the method, check the
 * caller is logged in, validate the input, hand clean data to the
 * repository, and turn the result into a response.
 */

require __DIR__ . '/../../src/bootstrap.php';

use App\RouteRepository;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$userId = current_user_id();
if ($userId === null) {
    json_response(['error' => 'Not signed in'], 401);
}

// PHP only populates $_POST for form encodings, so a JSON body is read raw.
$raw = file_get_contents('php://input');

try {
    $input = json_decode($raw ?: '', true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    json_response(['error' => 'Body is not valid JSON'], 400);
}

if (!is_array($input)) {
    json_response(['error' => 'Body must be a JSON object'], 400);
}

$name = trim((string) ($input['name'] ?? ''));
if ($name === '') {
    json_response(['error' => 'A name is required'], 422);
}
if (mb_strlen($name) > 160) {
    json_response(['error' => 'Name is too long (160 characters max)'], 422);
}

$description = isset($input['description'])
    ? trim((string) $input['description'])
    : null;
if ($description === '') {
    $description = null;
}

$distance = isset($input['distance_m']) && is_numeric($input['distance_m'])
    ? max(0, (int) $input['distance_m'])
    : null;

/*
 * Validate every coordinate before it reaches the database.
 *
 * The CHECK constraints in the schema would catch bad values too, but they
 * would surface as a PDOException — a 500, with a message about constraint
 * names that means nothing to whoever is using the page. Validating here
 * turns the same problem into a 422 that says what is wrong.
 *
 * Defence in depth: the app explains, the database guarantees.
 */
$rawPoints = $input['points'] ?? null;
if (!is_array($rawPoints) || count($rawPoints) < 2) {
    json_response(['error' => 'A route needs at least two points'], 422);
}
if (count($rawPoints) > 5000) {
    json_response(['error' => 'Too many points (5000 max)'], 422);
}

$points = [];
foreach ($rawPoints as $i => $pair) {
    if (!is_array($pair) || count($pair) < 2) {
        json_response(['error' => "Point {$i} is malformed"], 422);
    }

    // GeoJSON order: [longitude, latitude].
    $lng = $pair[0] ?? null;
    $lat = $pair[1] ?? null;

    if (!is_numeric($lng) || !is_numeric($lat)) {
        json_response(['error' => "Point {$i} is not numeric"], 422);
    }

    $lng = (float) $lng;
    $lat = (float) $lat;

    if ($lat < -90 || $lat > 90) {
        json_response(['error' => "Point {$i}: latitude out of range"], 422);
    }
    if ($lng < -180 || $lng > 180) {
        json_response(['error' => "Point {$i}: longitude out of range"], 422);
    }

    $points[] = [$lng, $lat];
}

$repo = new RouteRepository(pdo());

try {
    $id = $repo->create($userId, $name, $description, $distance, $points);
} catch (Throwable $e) {
    // Log the detail, tell the caller nothing. An exception message can
    // leak table names, file paths, even fragments of the query.
    error_log('Route create failed: ' . $e->getMessage());
    json_response(['error' => 'Could not save the route'], 500);
}

json_response(['id' => $id], 201);
