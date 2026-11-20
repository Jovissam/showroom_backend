<?php
// Applies migrations/*.sql in filename order, once each (tracked in schema_migrations).
//   php bin/migrate.php
// Creates the database named in config.php (DB_NAME) if it doesn't exist yet.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../config.php';

$server = new PDO('mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$db = getDB();
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$done = $db->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/../migrations/*.sql');
sort($files);
$applied = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        continue;
    }
    $sql = (string) file_get_contents($file);
    // Split on semicolons at line ends (migrations contain no procedures or triggers).
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $statement) {
        $statement = trim(preg_replace('/^\s*--.*$/m', '', $statement));
        if ($statement !== '') {
            $db->exec($statement);
        }
    }
    $db->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
    echo "applied $name\n";
    $applied++;
}
echo $applied ? "done ($applied applied)\n" : "nothing to apply\n";
