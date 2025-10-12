<?php
class Router {
    protected $routes = [
        'GET' => [],
        'POST' => [],
    ];
    protected $baseUrl;

    public function __construct($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function get($path, $handler)
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post($path, $handler)
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    protected function normalize($path)
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    protected function routePath($requestUri)
    {
        $uri = parse_url($requestUri, PHP_URL_PATH);
        // Remove base path (e.g., /project)
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');
        if ($basePath && $basePath !== '/') {
            if (strpos($uri, $basePath) === 0) {
                $uri = substr($uri, strlen($basePath));
            }
        }
        $path = '/' . trim($uri, '/');
        return $path === '//' ? '/' : $path;
    }

    public function dispatch($method, $requestUri)
    {
        $path = $this->routePath($requestUri);
        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            http_response_code(404);
            echo "<h1>404 Not Found</h1><p>No route for $method $path</p>";
            return;
        }

        if (is_callable($handler)) {
            return call_user_func($handler);
        }
        if (is_string($handler)) {
            // Format: Controller@method
            if (strpos($handler, '@') !== false) {
                list($controller, $methodName) = explode('@', $handler, 2);
                if (!class_exists($controller)) {
                    http_response_code(500);
                    echo "Controller $controller not found";
                    return;
                }
                $instance = new $controller();
                if (!method_exists($instance, $methodName)) {
                    http_response_code(500);
                    echo "Method $methodName not found in $controller";
                    return;
                }
                return $instance->$methodName();
            }
        }

        http_response_code(500);
        echo "Invalid route handler";
    }

    public function url($path = '/') {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }
}
