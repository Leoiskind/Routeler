<?php
declare(strict_types=1);
/** @var array<string, AuthProvider> $providers */
?>

<h1>Sign in</h1>

<?php if ($providers === []): ?>

    <div class="empty">
        <p>No sign-in method is configured.</p>
        <p>Set <code>GITHUB_CLIENT_ID</code> and <code>GITHUB_CLIENT_SECRET</code>
           in <code>.env</code>, then recreate the container.</p>
    </div>

<?php else: ?>

    <p class="hint">Routeler uses your existing account. It never sees or stores a password.</p>

    <div class="signin-options">
        <?php foreach ($providers as $provider): ?>
            <a class="button button--inline"
               href="/auth/login.php?with=<?= urlencode($provider->name()) ?>">
                Continue with <?= e($provider->label()) ?>
            </a>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
