<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

  OpenSB is free software: you can redistribute it and/or modify it under the 
  terms of the GNU Affero General Public License as published by the Free 
  Software Foundation, either version 3 of the License, or (at your option) any
  later version. 

  OpenSB is distributed in the hope that it will be useful, but WITHOUT ANY 
  WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
  FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more 
  details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

namespace Core;

class Router
{
    private array $routes = [];
    private mixed $fallbackHandler = null;

    public function add(string $path, mixed $handler, ?string $method = null): void
    {
        // automatically add SB_PRIVATE_PATH for file paths
        if (
            is_string($handler)
            && !str_contains($handler, SB_PRIVATE_PATH)
            && !str_starts_with($handler, '/')
        ) {
            $handler = SB_PRIVATE_PATH . '/pages/' . ltrim($handler, '/');
        }

        // if no method specified, just define for GET AND POST (we don't use any of the other methods, yet)
        $methods = $method ? [strtoupper($method)] : ['GET', 'POST'];

        foreach ($methods as $httpMethod) {
            $this->routes[$httpMethod][] = [
                'pattern' => $this->compilePattern($path),
                'original' => $path,
                'handler' => $handler
            ];
        }
    }

    public function redirect(string $from, string $to, int $statusCode = 302): void
    {
        $this->add($from, fn() => Utilities::redirect($to, $statusCode));
    }

    public function setFallback(mixed $handler): void
    {
        $this->fallbackHandler = $handler;
    }

    public function dispatch(?string $requestUri = null, ?string $requestMethod = null): void
    {
        $requestUri ??= $_SERVER['REQUEST_URI'] ?? '/';
        $requestMethod = strtoupper($requestMethod ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri = $this->normalizeUri($requestUri);

        foreach ($this->routes[$requestMethod] ?? [] as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->executeHandler($route['handler'], $params);
                return;
            }
        }

        $this->executeFallback();
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_quote($path, '#');
        $pattern = preg_replace('#\\\\\{(\w+)\\\\}#', '(?<$1>[^/]+)', $pattern);

        return '#^' . $pattern . '$#';
    }

    private function normalizeUri(string $uri): string
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        return rtrim($uri, '/') ?: '/';
    }

    private function executeHandler(mixed $handler, array $params = []): void
    {
        match (true) {
            is_callable($handler) => $handler($params),
            is_string($handler) && file_exists($handler) => $this->includeFile($handler, $params),
            default => $this->executeFallback()
        };
    }

    private function includeFile(string $file, array $params): void
    {
        extract($params, EXTR_SKIP);
        require $file;
    }

    private function executeFallback(): void
    {
        match (true) {
            is_callable($this->fallbackHandler) => ($this->fallbackHandler)(),
            is_string($this->fallbackHandler) && file_exists($this->fallbackHandler) => require $this->fallbackHandler,
            default => $this->default404()
        };
    }

    private function default404(): void
    {
        http_response_code(404);
        die("404");
    }
}
