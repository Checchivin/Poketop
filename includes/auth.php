<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_username(): ?string
{
    return $_SESSION['username'] ?? null;
}

function current_user_type(): ?string
{
    return $_SESSION['user_type'] ?? null;
}

function is_admin_user(): bool
{
    return current_user_type() === 'admin';
}

function require_login(): void
{
    if (current_user_id() === null) {
        redirect_to('login.php');
    }
}

function require_admin(): void
{
    require_login();

    if (!is_admin_user()) {
        redirect_to('dashboard.php');
    }
}

function redirect_for_user_type(): void
{
    if (is_admin_user()) {
        redirect_to('admin_dashboard.php');
    }

    redirect_to('dashboard.php');
}
