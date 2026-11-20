<?php
// Computes the automatic focal point for portfolio images that don't have one set by hand.
//   php bin/compute-focal.php          images still on 'auto' (safe to re-run)
//   php bin/compute-focal.php --all    also overwrite points an admin set by hand
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Core/Autoload.php';

use App\Repositories\ImageRepository;
use App\Services\PortfolioService;

$all = in_array('--all', $argv, true);
$rows = getDB()->query('SELECT * FROM portfolio_images' . ($all ? '' : " WHERE focal_source = 'auto'") . ' ORDER BY id')->fetchAll();
$repo = new ImageRepository();
foreach ($rows as $img) {
    [$x, $y] = PortfolioService::autoFocal($img);
    $repo->setFocal((int) $img['id'], $x, $y, 'auto');
    printf("image %-4d %4dx%-4d focal %.3f, %.3f\n", $img['id'], $img['width'], $img['height'], $x, $y);
}
echo count($rows) . " images updated\n";
