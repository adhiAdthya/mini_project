

<?php
// Centralized DB bootstrap for both legacy pages and MVC components.
// Provides:
// - $pdo  : PDO connection (preferred)
// - $conn : mysqli connection (backward compatibility if some pages still use it)
// - Helper functions: csrf_token(), verify_csrf($token), role_id($name)

// Load app config and PDO Database helper
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/Database.php';

// Establish a shared PDO connection via the app's Database class
try {
    /** @var PDO $pdo */
    $pdo = Database::connection();
} catch (Throwable $e) {
    // Fallback: create PDO directly from config if Database class fails for any reason
    $cfg = config('db');
    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s',
        $cfg['driver'], $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']
    );
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $options);
}

// Optional: also expose a mysqli connection for any legacy code that still expects $conn
// This keeps older include files working without immediate refactors.
try {
    $cfg = config('db');
    $conn = @new mysqli($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database'], (int)$cfg['port']);
    if (!$conn->connect_errno) {
        @$conn->set_charset($cfg['charset'] ?? 'utf8');
    }
} catch (Throwable $e) {
    // Silently ignore; prefer $pdo everywhere
}

// CSRF helpers used by legacy forms
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return is_string($token) && $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}

// Utility to get role id by name (used in signup.php)
if (!function_exists('role_id')) {
    function role_id(string $name): ?int {
        // Use the global $pdo initialized above
        global $pdo;
        if (!$pdo) return null;
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }
}

?>
