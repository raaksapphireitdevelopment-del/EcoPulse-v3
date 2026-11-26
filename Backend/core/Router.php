<?php
declare(strict_types=1);

namespace EcoPulse\Core;

/**
 * Lightweight REST Router supporting route parameters and middleware pipelines
 */
class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];

    public function use(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function options(string $path, mixed $handler): void
    {
        $this->addRoute('OPTIONS', $path, $handler, []);
    }

    private function addRoute(string $method, string $path, mixed $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => '/' . trim($path, '/'),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        // Execute global middleware
        foreach ($this->globalMiddleware as $middleware) {
            $response = $middleware($request);
            if ($response !== null) {
                return;
            }
        }

        $requestMethod = $request->getMethod();
        $requestPath = $this->normalizePath($request->getUri());

        $matchedRoute = null;
        $pathParams = [];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = $this->convertPathToRegex($route['path']);
            if (preg_match($pattern, $requestPath, $matches)) {
                $matchedRoute = $route;
                foreach ($matches as $key => $val) {
                    if (is_string($key)) {
                        $pathParams[$key] = $val;
                    }
                }
                break;
            }
        }

        if ($matchedRoute === null) {
            // Check if route exists under other method
            foreach ($this->routes as $route) {
                $pattern = $this->convertPathToRegex($route['path']);
                if (preg_match($pattern, $requestPath)) {
                    Response::error('HTTP Method not allowed for this endpoint.', 405, 'METHOD_NOT_ALLOWED');
                    return;
                }
            }

            Response::error(sprintf("Endpoint '%s' [%s] not found.", $requestPath, $requestMethod), 404, 'NOT_FOUND');
            return;
        }

        // Execute route-specific middleware
        foreach ($matchedRoute['middleware'] as $mw) {
            $result = is_callable($mw) ? $mw($request) : (new $mw())($request);
            if ($result !== null) {
                return;
            }
        }

        // Execute Controller action
        $handler = $matchedRoute['handler'];

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method($request, $pathParams);
            return;
        }

        if (is_callable($handler)) {
            $handler($request, $pathParams);
            return;
        }

        Response::error('Invalid route handler configuration.', 500, 'SERVER_ERROR');
    }

    private function normalizePath(string $uri): string
    {
        // Strip out base path if deployed in subdirectory like /ecopulse/Backend/
        // Matches everything from /api/ onwards or root
        $pos = strpos($uri, '/api');
        if ($pos !== false) {
            return substr($uri, $pos);
        }
        return '/' . trim($uri, '/');
    }

    private function convertPathToRegex(string $path): string
    {
        // Replaces :param with named subpattern (?P<param>[^/]+)
        $pattern = preg_replace('#:([a-zA-Z0-9_]+)#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
