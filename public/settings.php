<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/UserRepository.php';

$userId = current_user_id();
if ($userId === null) {
    redirect('/auth/login.php');
}

$users = new UserRepository(pdo());

// Look the user up by id. findByUsername needs a username we don't have
// yet, so one small query here.
$stmt = pdo()->prepare('SELECT username FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$username = $stmt->fetchColumn();

if ($username === false) {
    // The session points at a user who no longer exists.
    unset($_SESSION['user_id']);
    redirect('/');
}

$user   = $users->findByUsername((string) $username);
$errors = [];

// Values shown in the form: what's stored, until a submission replaces them.
$values = [
    'display_name' => (string) $user['display_name'],
    'bio'          => (string) ($user['bio'] ?? ''),
    'location'     => (string) ($user['location'] ?? ''),
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!csrf_valid()) {
        http_response_code(419);
        $errors[] = 'That form expired. Please try again.';
    } else {
        // Redisplay what they typed, not what was stored.
        $values = [
            'display_name' => trim((string) ($_POST['display_name'] ?? '')),
            'bio'          => trim((string) ($_POST['bio'] ?? '')),
            'location'     => trim((string) ($_POST['location'] ?? '')),
        ];

        if ($values['display_name'] === '') {
            $errors[] = 'Display name cannot be empty.';
        } elseif (mb_strlen($values['display_name']) > 120) {
            $errors[] = 'Display name is too long (120 characters max).';
        }

        if (mb_strlen($values['location']) > 120) {
            $errors[] = 'Location is too long (120 characters max).';
        }

        if (mb_strlen($values['bio']) > 1000) {
            $errors[] = 'Bio is too long (1000 characters max).';
        }

        if ($errors === []) {
            try {
                $users->updateProfile(
                    $userId,
                    $values['display_name'],
                    // Nullable columns get null, not '' — otherwise there are
                    // two kinds of empty and every read has to check both.
                    $values['bio'] !== '' ? $values['bio'] : null,
                    $values['location'] !== '' ? $values['location'] : null,
                );

                // POST-Redirect-GET: without this, refreshing re-submits.
                redirect('/settings.php?saved=1');

            } catch (Throwable $e) {
                error_log('Profile update failed: ' . $e->getMessage());
                $errors[] = 'Could not save your changes. Please try again.';
            }
        }
    }
}

render(
    'settings',
    [
        'user'   => $user,
        'values' => $values,
        'errors' => $errors,
        'saved'  => isset($_GET['saved']),
    ],
    'Routeler — Settings'
);
