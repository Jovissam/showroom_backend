<?php
// Copy to config.php and fill in real values. config.php is git-ignored: never commit secrets.
date_default_timezone_set('Africa/Lagos');

// CORS: the Nuxt site's origins. Must run before any output, on every request.
$_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$_allowedOrigins = [
    'https://example.com',          // production site (replace)
    'http://localhost:3000',        // nuxt dev / preview
    'http://127.0.0.1:3000',
];
if (in_array($_origin, $_allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $_origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('DB_HOST',    'localhost');
define('DB_NAME',    'jovisco');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+01:00'"); // Africa/Lagos (WAT)
    }
    return $pdo;
}

define('SITE_NAME',    'Jovisco');
define('FRONTEND_URL', 'http://localhost:3000');

// Signs admin session tokens. 64+ random characters, e.g. `php -r "echo bin2hex(random_bytes(32));"`.
// Changing it signs every admin out.
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'change-me-to-a-long-random-string');

// Public base URL of this API (no trailing slash).
define('API_URL', rtrim(getenv('API_URL') ?: 'http://localhost/jovisco', '/'));

// ---- Portfolio CMS ---------------------------------------------------------
// Largest accepted image upload, in MB (PHP upload_max_filesize/post_max_size must allow it).
define('PORTFOLIO_MAX_UPLOAD_MB', (int) (getenv('PORTFOLIO_MAX_UPLOAD_MB') ?: 10));
// Public URL of the uploads/ folder (images are served directly by Apache, never executed).
define('UPLOADS_URL', rtrim(getenv('UPLOADS_URL') ?: API_URL . '/uploads', '/'));
