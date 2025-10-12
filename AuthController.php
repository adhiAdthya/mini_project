<?php
class AuthController extends Controller {
    public function loginForm()
    {
        if (!empty($_SESSION['user'])) {
            return $this->redirect('');
        }
        $token = $this->csrfToken();
        return $this->view('auth.login', ['token' => $token]);
    }

    public function login()
    {
        $this->checkCsrf();
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        // Database-backed authentication
        try {
            $user = User::findByEmail($email);
            if ($user && (int)$user->is_active === 1 && password_verify($password, $user->password)) {
                $_SESSION['user'] = [
                    'id' => (int)$user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->role_name ?? null,
                ];
                return $this->redirect('');
            }
        } catch (Throwable $e) {
            // In debug you may want to surface more info; here we keep it generic
        }

        $error = 'Invalid credentials or inactive account.';
        $token = $this->csrfToken();
        return $this->view('auth.login', compact('error', 'token'));
    }

    public function logout()
    {
        session_destroy();
        return $this->redirect('login');
    }
}
