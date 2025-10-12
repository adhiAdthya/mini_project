<?php
class User {
    public $id;
    public $name;
    public $email;
    public $password;
    public $role_id;
    public $role_name;
    public $is_active;

    public static function findByEmail(string $email): ?User
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $user = new self();
        foreach ($row as $k => $v) {
            if (property_exists($user, $k)) {
                $user->$k = $v;
            }
        }
        $user->role_name = $row['role_name'] ?? null;
        return $user;
    }
}
