<?php
class Database {
    private static $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $cfg = config('db');
            $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['driver'], $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']
            );
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $options);
        }
        return self::$pdo;
    }
}
