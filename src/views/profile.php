<?php
declare(strict_types=1);
/**
 * A user's profile and their routes.
 *
 * @var array $user    from UserRepository::findByUsername()
 * @var array $routes  from RouteRepository::listByAuthor()
 */

// Initials for the avatar, from the display name: "Leo Morgan" -> "LM".
$initials = '';
foreach (preg_split('/\s+/', trim((string) $user['display_name'])) ?: [] as $word) {
    if ($word !== '' && $initials !== '' && mb_strlen($initials) >= 2) {
        break;
    }
    if ($word !== '') {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
}
if ($initials === '') {
    $initials = mb_strtoupper(mb_substr((string) $user['username'], 0, 1));
}

$joined = null;
if (!empty($user['created_at'])) {
    $dt = date_create($user['created_at']);
    $joined = $dt !== false ? $dt->format('F Y') : null;
}

$totalMetres = 0;
foreach ($routes as $r) {
    $totalMetres += (int) ($r['distance_m'] ?? 0);
}

$isOwnProfile = current_user_id() !== null && current_user_id() === (int) $user['id'];
?>

<div class="profile-header">
    <?php if (!empty($user['avatar_url'])): ?>
        <img class="avatar" src="<?= e($user['avatar_url']) ?>" alt="" width="72" height="72">
    <?php else: ?>
        <div class="avatar avatar--initials"><?= e($initials) ?></div>
    <?php endif; ?>

    <div class="profile-identity">
        <h1><?= e($user['display_name']) ?></h1>
        <p class="profile-handle">@<?= e($user['username']) ?></p>

        <?php if (!empty($user['location']) || $joined !== null): ?>
            <p class="profile-facts">
                <?php if (!empty($user['location'])): ?>
                    <?= e($user['location']) ?><?= $joined !== null ? ' &middot; ' : '' ?>
                <?php endif; ?>
                <?php if ($joined !== null): ?>
                    Member since <?= e($joined) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($user['bio'])): ?>
    <p class="profile-bio"><?= e($user['bio']) ?></p>
<?php endif; ?>

<?php
/*
 * Only two stats, both computed from real data.
 *
 * The old mockup showed Followers, Following and Rating as hardcoded
 * zeros. The follows and ratings tables exist but nothing writes to them
 * yet, so showing those tiles would be decoration rather than
 * information — the same problem as the placeholder pages. Add them when
 * the features exist.
 */
?>
<dl class="stats">
    <div class="stat">
        <dt>Routes</dt>
        <dd><?= count($routes) ?></dd>
    </div>
    <?php if ($totalMetres > 0): ?>
        <div class="stat">
            <dt>Total distance</dt>
            <dd><?= number_format($totalMetres / 1000, 1) ?> km</dd>
        </div>
    <?php endif; ?>
</dl>

<h2 class="section-heading">Routes</h2>

<?php if ($routes === []): ?>

    <div class="empty">
        <?php if ($isOwnProfile): ?>
            <p>You haven't drawn any routes yet.</p>
            <p><a href="/create.php">Draw your first one</a></p>
        <?php else: ?>
            <p>@<?= e($user['username']) ?> hasn't shared any routes yet.</p>
        <?php endif; ?>
    </div>

<?php else: ?>

    <?php foreach ($routes as $route): ?>
        <?= view('route-card', ['route' => $route]) ?>
    <?php endforeach; ?>

<?php endif; ?>
