<?php
    session_start();
    //Unsecure, only used because we don't have an absolute host address yet.
    define ("DEVELOPER_URL", "https://". $_SERVER['HTTP_HOST'] .$_SERVER['REQUEST_URI']."front_page.php");
    // preg_replace("\/[a-z]+\.php","/front_page.php","https://". $_SERVER['HTTP_HOST'] .$_SERVER['REQUEST_URI'])
    // Define the location of the service on the Computer Science server.
    define("AUTHENTICATION_SERVICE_URL", "http://studentnet.cs.manchester.ac.uk/authenticate/");

    // Define the location of CAS's logtout service on the Computer Science server.
    define("AUTHENTICATION_LOGOUT_URL", "http://studentnet.cs.manchester.ac.uk/systemlogout.php");

    // Locate the Authenticator class.
    require_once("Authenticator.php");

    Authenticator::validateUser();
    exit;
?>
