<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (current_user_id() === null) {
    redirect_to('login.php');
}

redirect_for_user_type();
