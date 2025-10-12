<?php
// Simple installer to create database (if missing) and import schema.sql
// Usage: Visit http://localhost/project/install.php in your browser

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load config directly (don't require db_connect because DB may not exist yet)
require_once __DIR__ . '/app/config/config.php';

function html($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$cfg = config('db');
$host = $cfg['host'];
$port = $cfg['port'];
$db   = $cfg['database'];
$user = $cfg['username'];
$pass = $cfg['password'];
$charset = $cfg['charset'];

$schemaFile = __DIR__ . '/database/schema.sql';

$messages = [];
$errors = [];

try {
    // Step 1: try connecting to the target DB
    $dsnWithDb = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $db, $charset);
    try {
        $pdo = new PDO($dsnWithDb, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $messages[] = "Connected to database '" . $db . "'.";
    } catch (PDOException $e) {
        // Unknown database (1049)? Create it.
        if (strpos($e->getMessage(), 'Unknown database') !== false || $e->getCode() == 1049) {
            $messages[] = "Database '" . $db . "' not found. Attempting to create it...";
            $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset);
            $pdoAdmin = new PDO($dsnNoDb, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdoAdmin->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`','``',$db) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $messages[] = "Database '" . $db . "' created (or already existed).";
            // Reconnect to the new DB
            $pdo = new PDO($dsnWithDb, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            throw $e;
        }
    }

    // Step 2: load and execute schema.sql
    if (!file_exists($schemaFile)) {
        throw new RuntimeException('Schema file not found at ' . $schemaFile);
    }
    $sql = file_get_contents($schemaFile);

    // Basic splitter: split on semicolons not in strings. For simplicity here, we will
    // execute with PDO->exec on the full content if the driver supports multi-statements.
    // MySQL PDO typically can handle multiple statements when emulation is enabled,
    // but we keep emulation off for safety and split manually as a fallback.

    $statements = [];
    $buffer = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);
    for ($i=0; $i<$len; $i++) {
        $ch = $sql[$i];
        $buffer .= $ch;
        if ($inString) {
            if ($ch === $stringChar) {
                // Check for escaped quote
                $escaped = ($i > 0 && $sql[$i-1] === '\\');
                if (!$escaped) {
                    $inString = false;
                    $stringChar = '';
                }
            }
            continue;
        }
        if ($ch === '\'' || $ch === '"') {
            $inString = true;
            $stringChar = $ch;
            continue;
        }
        if ($ch === ';') {
            $trim = trim($buffer);
            if ($trim !== '' && $trim !== ';') {
                $statements[] = $trim;
            }
            $buffer = '';
        }
    }
    $trim = trim($buffer);
    if ($trim !== '') {
        $statements[] = $trim;
    }

    $pdo->beginTransaction();
    foreach ($statements as $stmt) {
        // Skip comments-only statements
        $stmtNoComments = preg_replace('/^(--.*$|\/\*.*?\*\/)/ms', '', $stmt);
        if (trim($stmtNoComments) === '') continue;
        $pdo->exec($stmt);
    }
    $pdo->commit();

    $messages[] = 'Schema installed successfully.';
    $messages[] = 'You can now sign up as a new customer.';

} catch (Throwable $ex) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errors[] = $ex->getMessage();
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installer - Garage DB</title>
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#f5f7fa;margin:0;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.08);padding:24px;max-width:700px;width:100%}
.h{margin:0 0 10px;color:#2c3e50}
.p{color:#7f8c8d;margin:0 0 18px}
.alert{padding:10px;border-radius:8px;margin-bottom:10px}
.alert-danger{background:#fee2e2;color:#7f1d1d;border:1px solid #fecaca}
.alert-success{background:#e7f7ee;color:#0f5132;border:1px solid #badbcc}
.btn{background:#3498db;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer}
.btn:hover{opacity:.95}
</style>
</head>
<body>
    <div class="card">
        <h2 class="h">Installer</h2>
        <p class="p">This will create the database (if missing) and import the schema from <code>database/schema.sql</code> using the settings in <code>app/config/config.php</code>.</p>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?php echo html($e); ?></div>
        <?php endforeach; ?>
        <?php foreach ($messages as $m): ?>
            <div class="alert alert-success"><?php echo html($m); ?></div>
        <?php endforeach; ?>

        <div style="margin-top:10px;">
            <button class="btn" onclick="window.location.href='signup.php'">Go to Sign Up</button>
            <button class="btn" style="background:#2c3e50;margin-left:8px;" onclick="window.location.href='index.php'">Home</button>
        </div>
    </div>
</body>
</html>
