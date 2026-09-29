<?php
namespace App\Core;

class Router
{
    private $routes = [];
    private $notFoundHandler = null;

    public function add($methods, string $pattern, $handler): void
    {
        foreach ((array) $methods as $m) {
            $this->routes[] = [strtoupper($m), $pattern, $handler];
        }
    }

    public function get(string $pattern, $handler): void { $this->add('GET', $pattern, $handler); }
    public function post(string $pattern, $handler): void { $this->add('POST', $pattern, $handler); }

    public function notFound(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /** 兼容旧式 dispatch 调用 */
    public function dispatch(string $method, string $path): void
    {
        $this->handle($method, $path);
    }

    public function run(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
        ob_start();
        $this->handle($method, $path);
        return ob_get_clean();
    }

    protected function handle(string $method, string $path): void
    {
        foreach ($this->routes as [$m, $pattern, $handler]) {
            if ($m !== $method) {
                continue;
            }
            $regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '([^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                $this->callHandler($handler, array_values($matches));
                return;
            }
        }
        http_response_code(404);
        if ($this->notFoundHandler) {
            echo call_user_func($this->notFoundHandler);
            return;
        }
        echo View::render('errors/404', ['title' => '页面不存在'], 'layout');
    }

    protected function callHandler($handler, array $params): void
    {
        if (is_callable($handler)) {
            echo call_user_func_array($handler, $params);
            return;
        }
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$ctrl, $action] = explode('@', $handler, 2);
            $class = $this->resolveController($ctrl);
            if (class_exists($class) && method_exists($class, $action)) {
                $controller = new $class();
                echo $controller->$action(...$params);
                return;
            }
        }
        throw new \RuntimeException('路由处理器无效: ' . (is_string($handler) ? $handler : gettype($handler)));
    }

    protected function resolveController(string $name): string
    {
        // 支持 'Admin\XxxController' 子命名空间写法
        $candidates = [
            'App\\Controllers\\' . $name,
            $name,
        ];
        foreach ($candidates as $c) {
            if (class_exists($c)) {
                return $c;
            }
        }
        return $candidates[0];
    }
}
