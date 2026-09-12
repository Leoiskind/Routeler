<?php
// TODO: Check if user is already logged in, redirect to index.php if so
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Routler - Login</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <h1>Routler</h1>
            <p>Map and share your routes</p>
        </div>
        <div class="right-panel">
            <h2>Welcome to Routler!</h2>
            <!-- Redirects to university CAS login via index.php -->
            <a href="front_page.php" class="signin-btn">Sign in with University Account</a>
            <!-- TODO: After CAS login, redirect user to main map page -->
        </div>
    </div>
</body>
</html>