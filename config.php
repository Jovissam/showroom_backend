<?php
// Application configuration. Contains NO secrets: every value comes from the environment, or from
// the git-ignored .env file next to this one (see .env.example for the full list).
require_once __DIR__ . '/src/Core/Env.php';

use App\Core\Env;

Env::load(__DIR__ . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'Africa/Lagos'));

// CORS: must run before any output, on every request.
$_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($_origin !== '' && in_array($_origin, Env::list('CORS_ALLOWED_ORIGINS'), true)) {
    header("Access-Control-Allow-Origin: $_origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database.
define('DB_HOST', Env::get('DB_HOST', 'localhost'));
define('DB_NAME', Env::required('DB_NAME'));
define('DB_USER', Env::get('DB_USER', 'root'));
define('DB_PASS', Env::get('DB_PASS', ''));
define('DB_CHARSET', Env::get('DB_CHARSET', 'utf8mb4'));

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
        // Match the MySQL session time zone to PHP's.
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }
    return $pdo;
}

// Signs admin session tokens (changing it signs every admin out).
define('JWT_SECRET', Env::required('JWT_SECRET'));

define('SITE_NAME', Env::get('SITE_NAME', 'Jovisco'));
define('FRONTEND_URL', rtrim(Env::get('FRONTEND_URL', 'http://localhost:3000'), '/'));

// Public base URL of this API, and of the uploads/ folder (images are served directly by Apache).
define('API_URL', rtrim(Env::get('API_URL', 'http://localhost/jovisco'), '/'));
define('UPLOADS_URL', rtrim(Env::get('UPLOADS_URL') ?: API_URL . '/uploads', '/'));

// Largest accepted image upload, in MB (PHP upload_max_filesize/post_max_size must allow it).
define('PORTFOLIO_MAX_UPLOAD_MB', Env::int('PORTFOLIO_MAX_UPLOAD_MB', 10));
