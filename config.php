<?php
// Application configuration (no recursive includes)

// Global config store
global $CONFIG;
$CONFIG = [];

// Database settings (fill with your XAMPP MySQL credentials)
$CONFIG['db'] = [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'garage_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

// App settings
$CONFIG['app'] = [
    'name' => 'Garage Management System',
    'env' => 'local',
    'debug' => true,
    'base_url' => defined('BASE_URL') ? BASE_URL : '',
];

// Helper
if (!function_exists('config')) {
    function config($key = null, $default = null) {
        global $CONFIG;
        if ($key === null) return $CONFIG;
        $parts = explode('.', $key);
        $val = $CONFIG;
        foreach ($parts as $p) {
            if (!is_array($val) || !array_key_exists($p, $val)) return $default;
            $val = $val[$p];
        }
        return $val;
    }
}
