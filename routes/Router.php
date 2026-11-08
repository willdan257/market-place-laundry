<?php

namespace Routes;

use App\Controllers\HomeController;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, array $handler, array $middlewares): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function dispatch(string $uri, string $method): void
    {
        $parsedUrl = parse_url($uri, PHP_URL_PATH);
        
        // Normalize path (handle XAMPP subfolder like /marketplace-laundry or /marketplace-laundry/public)
        $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName !== '/' && !empty($scriptName) && str_starts_with($parsedUrl, $scriptName)) {
            $parsedUrl = substr($parsedUrl, strlen($scriptName));
        } else {
            $projDir = str_replace('\\', '/', dirname($scriptName));
            if ($projDir !== '/' && !empty($projDir) && str_starts_with($parsedUrl, $projDir)) {
                $parsedUrl = substr($parsedUrl, strlen($projDir));
            }
        }

        $parsedUrl = '/' . trim($parsedUrl, '/');
        if ($parsedUrl !== '/' && str_ends_with($parsedUrl, '/')) {
            $parsedUrl = rtrim($parsedUrl, '/');
        }

        foreach ($this->routes as $route) {
            $routeMethod = $route['method'];
            $reqMethod = strtoupper($method);
            if ($routeMethod !== $reqMethod && !($routeMethod === 'GET' && $reqMethod === 'HEAD')) {
                continue;
            }

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $route['path']);
            $pattern = "#^" . $pattern . "$#";

            if (preg_match($pattern, $parsedUrl, $matches)) {
                // Execute Middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $mw = is_string($middleware) ? new $middleware() : $middleware;
                    if (method_exists($mw, 'handle')) {
                        $passed = $mw->handle();
                        if (!$passed) {
                            return;
                        }
                    }
                }

                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$controllerClass, $action] = $route['handler'];

                if (class_exists($controllerClass)) {
                    $controller = new $controllerClass();
                    if (method_exists($controller, $action)) {
                        call_user_func_array([$controller, $action], $params);
                        return;
                    }
                }
            }
        }

        // 404 Route handler
        $homeController = new HomeController();
        $homeController->notFound();
    }
}
