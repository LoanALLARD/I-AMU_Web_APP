<?php

declare(strict_types=1);

namespace Core;

/**
 * Minimal HTTP router with dynamic path parameters.
 *
 * Routes are declared as: `$router->add('GET', '/sessions/{id}', $cb)`.
 * Any `{name}` placeholder is converted to a named regex group; matched
 * values are passed to the callback as positional arguments, in declaration
 * order.
 *
 * Refactor of the original literal-match version. The dispatch entry point
 * is renamed `dispatch()` (was `compare()` - kept as an alias for the few
 * existing callers).
 */
final class Router
{
    /**
     * @var list<array{method:string, regex:string, paramNames:list<string>, callback:callable}>
     */
    private array $routes = [];

    /**
     * Registers a route.
     *
     * @param string   $path     e.g. "/sessions/{id}/edit"
     * @param callable $callback Receives one argument per `{placeholder}`.
     */
    public function add(string $method, string $path, callable $callback): void
    {
        $paramNames = [];

        // Convert {name} to (?P<name>[^/]+), record param names in order.
        $regexPath = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];
                return '(?P<' . $m[1] . '>[^/]+)';
            },
            $path
        ) ?? $path;

        // Anchor both ends so "/sessions" never matches "/sessions/12".
        $this->routes[] = [
            'method'     => strtoupper($method),
            'regex'      => '#^' . $regexPath . '$#',
            'paramNames' => $paramNames,
            'callback'   => $callback,
        ];
    }

    /**
     * Matches the request against registered routes and invokes the callback.
     * Sends a 404 response if no route matches.
     */
    public function dispatch(string $requestUrl, string $requestedMethod): void
    {
        $path   = parse_url($requestUrl, PHP_URL_PATH) ?: '/';
        $method = strtoupper($requestedMethod);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            // Extract params in declaration order so the callback can rely on it.
            $args = [];
            foreach ($route['paramNames'] as $name) {
                $args[] = $matches[$name] ?? null;
            }

            ($route['callback'])(...$args);
            return;
        }

        // No route matched - let the global handler render the 404 page.
        throw new HttpException(404, 'Cette page n\'existe pas.');
    }

    /**
     * Backwards-compatible alias for callers that still use the old name.
     *
     * @deprecated Use {@see dispatch()} instead.
     */
    public function compare(string $requestUrl, string $requestedMethod): void
    {
        $this->dispatch($requestUrl, $requestedMethod);
    }
}
