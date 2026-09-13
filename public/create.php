<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

if (current_user_id() === null) {
    redirect('/auth/login.php');
}

render('route-create', [], 'Routeler — Draw a route');
