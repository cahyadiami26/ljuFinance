<?php

declare(strict_types=1);

namespace Lju\Core;

/**
 * Router sederhana berbasis path.
 *
 * Rute didefinisikan di app/routes.php:
 *   $router->get('master/coa', [CoaController::class, 'index'], 'coa.view');
 *   $router->post('master/user/{id}/role', [UserController::class, 'updateRole'], 'user.manage');
 *
 * Parameter dinamis memakai {nama} dan diteruskan sebagai argumen controller.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:array,permission:?string,options:array}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler */
    public function add(string $method, string $pattern, array $handler, ?string $permission = null, array $options = []): void
    {
        $pattern = trim($pattern, '/');
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';

        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'regex'      => $regex,
            'handler'    => $handler,
            'permission' => $permission,
            'options'    => $options,
        ];
    }

    /** @param array{0:class-string,1:string} $handler */
    public function get(string $pattern, array $handler, ?string $permission = null, array $options = []): void
    {
        $this->add('GET', $pattern, $handler, $permission, $options);
    }

    /** @param array{0:class-string,1:string} $handler */
    public function post(string $pattern, array $handler, ?string $permission = null, array $options = []): void
    {
        $this->add('POST', $pattern, $handler, $permission, $options);
    }

    public static function dispatchFromGlobals(): void
    {
        $router = new self();
        require BASE_PATH . '/app/routes.php';
        $router->run((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), self::resolvePath());
    }

    public static function resolvePath(): string
    {
        $path = '';

        if (isset($_GET['r']) && is_string($_GET['r'])) {
            $path = $_GET['r'];
        } elseif (!empty($_SERVER['PATH_INFO'])) {
            $path = (string) $_SERVER['PATH_INFO'];
        } else {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
            $base = App::baseUrl();
            if ($base !== '' && str_starts_with($uri, $base)) {
                $uri = substr($uri, strlen($base));
            }
            $path = preg_replace('#/index\.php$#', '', $uri) ?? $uri;
        }

        $path = trim(rawurldecode($path), '/');
        return $path === '' ? 'dashboard' : $path;
    }

    public function run(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = trim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[] = $value;
                }
            }

            $this->execute($route, $params);
            return;
        }

        App::abort(404, 'Halaman "' . $path . '" tidak ditemukan.');
    }

    /** @param array{handler:array,permission:?string,options:array} $route */
    private function execute(array $route, array $params): void
    {
        $options = $route['options'];
        $public = (bool) ($options['public'] ?? false);

        if (!$public) {
            Auth::guard();
        }

        if (!$public && !Auth::can((string) ($route['permission'] ?? ''))) {
            App::abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && empty($options['no_csrf'])) {
            if (!Csrf::verify()) {
                Flash::error('Sesi formulir telah kedaluwarsa. Silakan ulangi pengisian.');
                back();
            }
        }

        [$class, $action] = $route['handler'];
        if (!class_exists($class) || !method_exists($class, $action)) {
            App::abort(500, 'Handler rute tidak ditemukan: ' . $class . '::' . $action);
        }

        $controller = new $class();
        $controller->{$action}(...$params);
    }

    /** @return array<int,string> */
    public function patterns(): array
    {
        return array_map(static fn ($r) => $r['method'] . ' ' . $r['pattern'], $this->routes);
    }
}
