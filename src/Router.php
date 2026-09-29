<?php

declare(strict_types=1);

namespace App;

/**
 * URLと処理の対応表。'/e/{slug}' のように書くと slug を引数で受け取れる。
 */
final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    /** 表示（GET）と送信（POST）を同じ処理で受ける（入力フォーム用） */
    public function form(string $path, callable $handler): void
    {
        $this->get($path, $handler);
        $this->post($path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                $params = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
                $handler(...$params);
                return;
            }
        }

        abort_not_found();
    }
}
