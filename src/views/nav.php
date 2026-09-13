<?php
declare(strict_types=1);
/**
 * The site's one navigation bar.
 *
 * This replaces the four hand-copied copies in front_page.php, profile.php,
 * create_route.php and view-profile.php — all of which hardcoded
 * "Your username" and pointed "Logout" at the login page.
 */

$userId  = current_user_id();
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');

/** Mark the link for the page we are on. */
$active = static fn (string $file): string => $current === $file ? ' aria-current="page"' : '';
?>
<nav class="navbar">
    <a href="/" class="nav-brand">Routeler</a>

    <div class="nav-links">
        <a href="/"<?= $active('index.php') ?>>Home</a>
        <?php if ($userId !== null): ?>
            <a href="/create.php"<?= $active('create.php') ?>>Create route</a>
            <a href="/profile.php"<?= $active('profile.php') ?>>Profile</a>
            <a href="/settings.php"<?= $active('settings.php') ?>>Settings</a>
        <?php endif; ?>
    </div>

    <div class="nav-account">
        <?php if ($userId !== null): ?>
            <a href="/auth/logout.php" class="nav-signout">Sign out</a>
        <?php else: ?>
            <a href="/auth/login.php" class="nav-signin">Sign in</a>
        <?php endif; ?>
    </div>
</nav>
