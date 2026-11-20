<?php

// Front controller: every /api request is routed through here.
// Errors are logged, never printed into JSON responses.
ini_set('display_errors', '0');

require __DIR__ . '/config.php';
require __DIR__ . '/src/Core/Autoload.php';

use App\Core\AppException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

// Load Composer deps if any are installed.
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Record PHP warnings/notices without changing behaviour, and fatal errors on shutdown.
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) {
        return false; // suppressed with @
    }
    $level = in_array($errno, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true) ? 'warning' : 'info';
    Logger::log($level, 'php', $errstr, ['file' => basename($errfile) . ':' . $errline, 'errno' => $errno]);
    return false; // continue with PHP's normal handling
});
register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::critical('php', 'Fatal error: ' . $e['message'], ['file' => basename($e['file']) . ':' . $e['line']]);
    }
});

$request = new Request();
$router = new Router();

(require __DIR__ . '/routes/api.php')($router);

try {
    $router->dispatch($request);
} catch (AppException $e) {
    if ($e->status() >= 500) {
        Logger::error('app', $e->getMessage(), ['path' => $request->path(), 'method' => $request->method(), 'status' => $e->status()]);
    }
    Response::error($e->getMessage(), $e->status(), $e->errors());
} catch (\Throwable $e) {
    // Full details stay server-side; the client only gets a generic message.
    error_log('[jovisco] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Logger::critical('app', get_class($e) . ': ' . $e->getMessage(), [
        'path'   => $request->path(),
        'method' => $request->method(),
        'file'   => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
    Response::error('An unexpected error occurred', 500);
}
