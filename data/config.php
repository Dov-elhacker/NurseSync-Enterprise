<?php
/**
 * NurseSync Enterprise — Shared configuration, session, auth & CSRF helpers.
 * Include this at the very top of every page (before any HTML output).
 */

// Harden session cookies a little before starting the session
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/sqlsrv_stubs.php';

// ---------------------------------------------------------------------
// Admin credentials
// ---------------------------------------------------------------------
// There is no Users table yet, so a single admin account lives here.
// Do NOT store the plain password. Generate a hash once with
// generate_hash.php, paste it below, then delete generate_hash.php.
// ---------------------------------------------------------------------
define('NS_ADMIN_USERNAME', 'david');

define('NS_ADMIN_PASSWORD_HASH', '$2y$10$.WrjyccBCsRp5x9BGbYKb.K1yMp5i5ZglcfgYgzWpkPX8CzOBqe5y');

// ---------------------------------------------------------------------
// Auth helpers
// ---------------------------------------------------------------------
function ns_is_logged_in(): bool {
    return isset($_SESSION['ns_user']);
}

function ns_require_login(): void {
    if (!ns_is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

function ns_current_user(): string {
    return $_SESSION['ns_user'] ?? '';
}

function ns_db_connection() {
    global $conn;
    return $conn;
}

// ---------------------------------------------------------------------
// CSRF helpers — every state-changing form/link must use these
// ---------------------------------------------------------------------
function ns_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function ns_csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(ns_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function ns_verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('<div style="font-family:sans-serif; max-width:520px; margin:60px auto; text-align:center;">
                <h2 style="color:#B93B2C;">Request could not be verified</h2>
                <p>Your session may have expired. Please go back and try again.</p>
                <a href="dashboard.php">Return to Dashboard</a>
             </div>');
    }
}

// ---------------------------------------------------------------------
// Small input helpers
// ---------------------------------------------------------------------
function ns_clean(string $value): string {
    return trim($value);
}

function ns_out($value): string {
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
