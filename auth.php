<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db_connect.php';

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function require_role($roles) {
    require_login();
    $user = current_user();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($user['role'] ?? null, $roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}
