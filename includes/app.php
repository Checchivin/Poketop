<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/config.php';

function app_db(): mysqli
{
    static $mysqli = null;

    if ($mysqli instanceof mysqli) {
        return $mysqli;
    }

    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_errno) {
        http_response_code(500);
        exit('DB connection failed: ' . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');

    return $mysqli;
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_post_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash_set(string $key, string $message, string $type = 'info'): void
{
    $_SESSION['_flash'][$key] = [
        'message' => $message,
        'type' => $type,
    ];
}

function flash_get(string $key): ?array
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $flash = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    return $flash;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
