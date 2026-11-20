<?php
// Admin accounts CLI. There is no public sign-up: admins are created here.
//   php bin/admin.php create --email=you@example.com --name="Your Name"   (prompts for password)
//   php bin/admin.php create --email=... --name=... --password=...        (non-interactive)
//   php bin/admin.php password --email=...                                (reset; ends all sessions)
//   php bin/admin.php disable --email=...   |   enable --email=...   |   delete --email=...
//   php bin/admin.php list
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../config.php';

$cmd = $argv[1] ?? 'help';
$opts = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z]+)=(.*)$/s', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    }
}
$db = getDB();
$fail = function (string $msg): void {
    fwrite(STDERR, "error: $msg\n");
    exit(1);
};
$password = function () use ($opts, $fail): string {
    $p = $opts['password'] ?? null;
    if ($p === null) {
        echo 'Password (min 10 characters, typing is visible): ';
        $p = trim((string) fgets(STDIN));
    }
    if (mb_strlen($p) < 10) {
        $fail('password must be at least 10 characters');
    }
    return password_hash($p, PASSWORD_DEFAULT);
};
$email = strtolower(trim($opts['email'] ?? ''));

switch ($cmd) {
    case 'create':
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fail('--email is required');
        }
        $name = trim($opts['name'] ?? '') ?: 'Administrator';
        $exists = $db->prepare('SELECT 1 FROM admins WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $fail("an admin with email $email already exists");
        }
        $db->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)')->execute([$name, $email, $password()]);
        echo "created admin $email\n";
        break;
    case 'password':
        $stmt = $db->prepare('UPDATE admins SET password_hash = ?, token_version = token_version + 1 WHERE email = ?');
        $stmt->execute([$password(), $email]);
        echo $stmt->rowCount() ? "password updated for $email (all sessions ended)\n" : "no admin with email $email\n";
        break;
    case 'disable':
    case 'enable':
        $stmt = $db->prepare('UPDATE admins SET status = ?, token_version = token_version + 1 WHERE email = ?');
        $stmt->execute([$cmd === 'disable' ? 'disabled' : 'active', $email]);
        echo $stmt->rowCount() ? "$email is now " . ($cmd === 'disable' ? 'disabled' : 'active') . "\n" : "no admin with email $email\n";
        break;
    case 'delete':
        $stmt = $db->prepare('DELETE FROM admins WHERE email = ?');
        $stmt->execute([$email]);
        echo $stmt->rowCount() ? "deleted $email\n" : "no admin with email $email\n";
        break;
    case 'list':
        foreach ($db->query('SELECT email, name, status, last_login_at FROM admins ORDER BY id') as $a) {
            printf("%-40s %-24s %-9s last login: %s\n", $a['email'], $a['name'], $a['status'], $a['last_login_at'] ?? 'never');
        }
        break;
    default:
        echo file_get_contents(__FILE__, false, null, 0, 700);
}
