<?php
declare(strict_types=1);

namespace Tendari\Core;

final class App
{
    private array $routes = [];
    private string $base;

    public function __construct(string $basePath = '')
    {
        $this->base = $basePath;
    }

    public function get(string $path, callable $handler, string $auth = ''): void
    {
        $this->map('GET', $path, $handler, $auth);
    }

    public function post(string $path, callable $handler, string $auth = ''): void
    {
        $this->map('POST', $path, $handler, $auth);
    }

    private function map(string $method, string $path, callable $handler, string $auth): void
    {
        $this->routes[] = ['method' => $method, 'path' => $path, 'handler' => $handler, 'auth' => $auth];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Some nginx + PHP-FPM setups pass the front controller as the URI
        // after the directory-index rewrite (e.g. /index.php or /index.php/foo).
        if ($uri === '/index.php' || str_starts_with($uri, '/index.php/')) {
            $uri = substr($uri, strlen('/index.php'));
            if ($uri === '' || $uri === false) {
                $uri = '/';
            }
        }

        $path = '/' . trim(preg_replace('#^' . preg_quote($this->base, '#') . '#', '', $uri), '/');
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        // CSRF: required on every POST (webhooks excluded — they verify their own signatures)
        if ($method === 'POST' && !str_starts_with($path, '/telegram/webhook') && !str_starts_with($path, '/stripe/webhook')) {
            Auth::checkCsrf();
        }

        foreach ($this->routes as $route) {
            $params = $this->matchPath($route['path'], $path);
            if ($params === null || $route['method'] !== $method) {
                continue;
            }
            if ($route['auth'] === 'user') {
                Auth::requireUser();
            } elseif ($route['auth'] === 'admin') {
                Auth::requireAdmin();
            } elseif ($route['auth'] === 'guest') {
                Auth::requireGuest();
            }
            try {
                $route['handler']($params);
            } catch (\Throwable $e) {
                http_response_code(500);
                logger('error', 'Route error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
                if (is_htmx()) {
                    echo '<div class="notification is-danger">Something went wrong. Please try again.</div>';
                } else {
                    echo '<pre>' . e($e->getMessage()) . '</pre>';
                }
            }
            return;
        }
        http_response_code(404);
        if (is_htmx()) {
            echo '<div class="notification is-warning">Page not found.</div>';
        } else {
            render('pages/404', ['title' => 'Not found']);
        }
    }

    /** @return array|null named params or null when no match */
    private function matchPath(string $pattern, string $path): ?array
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (preg_match($regex, $path, $m)) {
            return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        return null;
    }
}
