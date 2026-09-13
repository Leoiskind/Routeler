<?php
declare(strict_types=1);
/**
 * Edit your own profile.
 *
 * @var array $user    the stored row
 * @var array $values  what goes in the fields — stored values, or the
 *                     submission being corrected
 * @var array $errors  messages to show, empty when there are none
 * @var bool  $saved   arrived here from a successful save
 */
?>

<p class="breadcrumb"><a href="/profile.php">&larr; Back to your profile</a></p>

<h1>Settings</h1>
<p class="profile-handle">Signed in as @<?= e($user['username']) ?></p>

<?php if ($saved && $errors === []): ?>
    <p class="form-success">Your profile has been updated.</p>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="form-error">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/settings.php" class="route-form">
    <?= csrf_field() ?>

    <label class="field">
        <span>Display name</span>
        <input type="text"
               name="display_name"
               id="display_name"
               maxlength="120"
               required
               value="<?= e($values['display_name']) ?>">
    </label>

    <label class="field">
        <span>Bio</span>
        <textarea name="bio" id="bio" rows="4" maxlength="1000"><?= e($values['bio']) ?></textarea>
    </label>

    <label class="field">
        <span>Location</span>
        <input type="text"
               name="location"
               id="location"
               maxlength="120"
               value="<?= e($values['location']) ?>">
    </label>

    <button type="submit" class="button">Save changes</button>
</form>

<p class="hint" style="margin-top:18px">
    Your handle, <strong>@<?= e($user['username']) ?></strong>, cannot be changed here —
    it appears in the address of every route you share.
</p>
