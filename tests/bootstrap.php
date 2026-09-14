<?php
declare(strict_types=1);

// Tests get the autoloader and nothing else. No session, no output, no
// database connection — src/bootstrap.php is for serving pages, and
// running it here would start a session on the command line.
require_once __DIR__ . '/../vendor/autoload.php';
