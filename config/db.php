<?php
declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'routeler';
$user = getenv('DB_USER') ?: 'routeler';
$pass = getenv('DB_PASS');

if ($pass===false){
    http_response_code(500);
    exit('DB_PASS is not set in the environment');
}

$dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES      => false,
];

/*
 * Managed databases (Aiven, PlanetScale, RDS) refuse unencrypted
 * connections. Point DB_SSL_CA at the provider's CA certificate and PDO
 * will both encrypt the connection and verify that the server presenting
 * it is really theirs — encryption without verification would still let
 * someone in the middle impersonate the database.
 *
 * The certificate is a public key, not a secret: it is safe to commit.
 * Empty locally, where the database is a container on the same network.
 */
$sslCa = getenv('DB_SSL_CA') ?: '';
if ($sslCa !== '') {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
}

try {
    // Returned to pdo() in bootstrap.php rather than assigned to a
    // variable this file leaves behind for its caller to find.
    return new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    /*
     * Free tiers sleep. Render spins the app down after ~15 minutes idle
     * and Aiven powers the database off after a longer stretch, so a
     * visitor can arrive to find the database still waking up.
     *
     * The default behaviour here would be an uncaught PDOException: a
     * stack trace naming the host, the database and the username, which
     * is both useless to the visitor and a gift to anyone scanning. Log
     * the detail, show a plain page, and send 503 with Retry-After so
     * crawlers understand it is temporary rather than gone.
     */
    error_log('Database connection failed: ' . $e->getMessage());

    http_response_code(503);
    header('Retry-After: 30');
    header('Content-Type: text/html; charset=utf-8');

    exit(<<<HTML
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Routeler — waking up</title>
        <style>
            body { background:#1a1a2e; color:#eaeaea; font-family:Arial, sans-serif;
                   display:grid; place-items:center; height:100vh; margin:0; text-align:center; }
            h1 { color:#e94560; font-size:1.5rem; }
            p  { color:#aaa; }
        </style>
    </head>
    <body>
        <div>
            <h1>Routeler is waking up</h1>
            <p>The free database goes to sleep when nobody is using it.<br>
               Give it a moment and refresh.</p>
        </div>
    </body>
    </html>
    HTML);
}
