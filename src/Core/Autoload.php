<?php

// Minimal PSR-4-ish autoloader: App\Foo\Bar -> src/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// Composer packages (PHPMailer). Loaded here so CLI scripts in bin/ get them too, not just
// index.php — without it, Mailer silently skipped every email sent from cron jobs.
if (is_file(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
