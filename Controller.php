<?php
class Controller {
    protected function view($view, $data = [])
    {
        extract($data);
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $viewFile = BASE_PATH . '/app/views/' . str_replace('.', '/', $view) . '.php';
        $layout = BASE_PATH . '/app/views/layouts/main.php';
        ob_start();
        if (file_exists($viewFile)) {
            include $viewFile;
        } else {
            echo "<p>View $view not found.</p>";
        }
        $content = ob_get_clean();
        include $layout;
    }

    protected function redirect($path)
    {
        $url = (defined('BASE_URL') ? BASE_URL : '') . '/' . ltrim($path, '/');
        header('Location: ' . $url);
        exit;
    }

    protected function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function csrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function checkCsrf()
    {
        if (!isset($_POST['_token']) || $_POST['_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(419);
            echo 'CSRF token mismatch.';
            exit;
        }
    }
}
