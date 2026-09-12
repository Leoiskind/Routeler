<?php
    session_start();
    // Unsecure fallback only used until a production host is configured.
    $baseUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    define("DEVELOPER_URL", rtrim($baseUrl, "/") . "/public/front_page.php");

    // Define the location of the service on the Computer Science server.
    define("AUTHENTICATION_SERVICE_URL", "http://studentnet.cs.manchester.ac.uk/authenticate/");

    // Define the location of CAS's logout service on the Computer Science server.
    define("AUTHENTICATION_LOGOUT_URL", "http://studentnet.cs.manchester.ac.uk/systemlogout.php");

    // Locate the Authenticator class.
    require_once __DIR__ . "/src/Authenticator.php";

    Authenticator::validateUser();
    exit;
?>
