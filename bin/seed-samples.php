<?php
// Seeds SAMPLE portfolio projects so the site and admin can be tried out before real work is added.
//   php bin/seed-samples.php [--images=<dir>]   add the samples (skips ones that already exist)
//   php bin/seed-samples.php --remove           delete every sample project and its image files
//
// Samples are identified by a slug starting with "sample-" and say so in their description.
// Their images are the site's own 3D renders (Nuxt: public/images/furniture), NOT photos of real
// installations; replace them with real project photos before launch.
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$_SERVER['REQUEST_METHOD'] = 'CLI';
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Core/Autoload.php';

use App\Core\ImageProcessor;
use App\Repositories\CategoryRepository;
use App\Repositories\ImageRepository;
use App\Services\ImageStorage;
use App\Services\PortfolioService;

$opts = getopt('', ['images:', 'remove']);
$db = getDB();
$service = new PortfolioService();

if (isset($opts['remove'])) {
    $ids = $db->query("SELECT id FROM portfolio_projects WHERE slug LIKE 'sample-%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        $service->delete((int) $id);
    }
    echo 'removed ' . count($ids) . " sample projects\n";
    exit(0);
}

$dir = rtrim($opts['images'] ?? 'C:/Users/Hp/Documents/VUE/jovisco/public/images/furniture', '/\\');
if (!is_dir($dir)) {
    fwrite(STDERR, "images folder not found: $dir (pass --images=<dir>)\n");
    exit(1);
}

$note = "Sample project: the details are illustrative and the images are 3D renders, not photos of a real installation. Replace or delete it before launch.\n\n";
$samples = [
    ['slug' => 'sample-walnut-sliding-wardrobe', 'category' => 'wardrobes', 'title' => 'Sliding-door wardrobe with walnut finish',
     'description' => "A floor-to-ceiling wardrobe for a master bedroom, with full-height sliding doors, a long hanging rail, shelves and a bank of drawers.\n\nSoft-close runners throughout, and the doors finished in a warm walnut-veneer plywood.",
     'materials' => 'Walnut-veneer plywood, white melamine carcass, aluminium sliding track', 'dimensions' => '2400 × 600 × 2600 mm',
     'location' => 'Lekki, Lagos', 'date' => '2026-08-14', 'featured' => true,
     'images' => ['wardrobe-modern-01.webp' => 'Sliding-door wardrobe with walnut doors', 'wardrobe-graphite-01.webp' => 'Alternative graphite finish for the same layout']],
    ['slug' => 'sample-marble-effect-wardrobe', 'category' => 'wardrobes', 'title' => 'Two-door wardrobe in marble-effect laminate',
     'description' => 'A compact two-door wardrobe for a guest room, finished in a white marble-effect laminate with slim handles.',
     'materials' => 'Marble-effect laminate on plywood, brushed steel handles', 'dimensions' => '1200 × 600 × 2200 mm',
     'location' => 'Ikoyi, Lagos', 'date' => '2026-06-02', 'featured' => false,
     'images' => ['wardrobe-marble-01.webp' => 'Two-door wardrobe in white marble-effect laminate']],
    ['slug' => 'sample-oak-walk-in-closet', 'category' => 'wardrobes', 'title' => 'Oak walk-in closet',
     'description' => 'Open shelving, hanging space and drawers wrapped around a dressing room, all in light oak plywood.',
     'materials' => 'Oak-veneer plywood, LED strip lighting', 'dimensions' => '3200 × 2400 × 2600 mm (L-shape)',
     'location' => 'Maitama, Abuja', 'date' => '2026-03-20', 'featured' => false,
     'images' => ['closet-oak-01.webp' => 'Walk-in closet with oak shelving and hanging rails']],
    ['slug' => 'sample-handleless-l-kitchen', 'category' => 'kitchen-cabinets', 'title' => 'White and charcoal L-shaped kitchen',
     'description' => "A handleless L-shaped kitchen with white wall units, charcoal base units and a stone worktop.\n\nTall units hide the fridge and pantry storage.",
     'materials' => 'Gloss-white and charcoal laminate on plywood, quartz worktop', 'dimensions' => '3600 × 2700 mm (L-shape)',
     'location' => 'Victoria Island, Lagos', 'date' => '2026-09-30', 'featured' => true,
     'images' => ['kitchen-l-01.webp' => 'L-shaped kitchen with white wall units and charcoal base units', 'kitchen-line-01.webp' => 'Straight-run variant of the same kitchen']],
    ['slug' => 'sample-u-shaped-grey-kitchen', 'category' => 'kitchen-cabinets', 'title' => 'U-shaped kitchen in grey',
     'description' => 'A U-shaped family kitchen with plenty of worktop space, finished in a soft grey matt laminate.',
     'materials' => 'Grey matt laminate on plywood, granite worktop', 'dimensions' => '3000 × 3000 mm (U-shape)',
     'location' => 'GRA, Port Harcourt', 'date' => '2026-05-11', 'featured' => false,
     'images' => ['kitchen-u-01.webp' => 'U-shaped kitchen with grey cabinets', 'kitchen-island-01.webp' => 'Kitchen island option in gloss white']],
    ['slug' => 'sample-slatted-tv-wall', 'category' => 'tv-consoles', 'title' => 'Slatted TV feature wall',
     'description' => 'A full-width TV wall with vertical slats, a floating console and concealed cable management.',
     'materials' => 'Oak-veneer slats, black laminate console, LED backlight', 'dimensions' => '3600 × 450 × 2700 mm',
     'location' => 'Wuse 2, Abuja', 'date' => '2026-10-05', 'featured' => true,
     'images' => ['tv-wall-slat-01.webp' => 'Slatted TV wall with floating console', 'tv-wall-oak-01.webp' => 'Oak panel version of the TV wall', 'tv-wall-gloss-01.webp' => 'Gloss panel version of the TV wall']],
    ['slug' => 'sample-floating-tv-console', 'category' => 'tv-consoles', 'title' => 'Floating TV console',
     'description' => 'A low wall-hung console with push-to-open doors for a compact living room.',
     'materials' => 'Plywood with oak finish', 'dimensions' => '1800 × 400 × 450 mm',
     'location' => 'Yaba, Lagos', 'date' => '2026-02-18', 'featured' => false,
     'images' => ['tv-console-01.webp' => 'Floating TV console with oak finish']],
    ['slug' => 'sample-cubby-bookcase', 'category' => 'shelving-and-storage', 'title' => 'Staggered cubby bookcase',
     'description' => 'A playful bookcase with staggered cubbies for a home office, mixing books and display pieces.',
     'materials' => 'Birch plywood', 'dimensions' => '1800 × 350 × 2000 mm',
     'location' => 'Bodija, Ibadan', 'date' => '2026-07-07', 'featured' => false,
     'images' => ['bookcase-cubby-01.webp' => 'Staggered cubby bookcase in birch plywood', 'shelving-corner-01.webp' => 'Corner shelving unit from the same room', 'bookcase-marble-01.webp' => 'Marble-effect bookcase variant']],
];

$categories = new CategoryRepository();
$images = new ImageRepository();
$added = 0;
foreach ($samples as $s) {
    $exists = $db->prepare('SELECT 1 FROM portfolio_projects WHERE slug = ?');
    $exists->execute([$s['slug']]);
    if ($exists->fetch()) {
        echo "skip  {$s['slug']} (exists)\n";
        continue;
    }
    $cat = $categories->findBySlug($s['category']);
    $project = $service->create([
        'title' => $s['title'], 'slug' => $s['slug'], 'category_id' => $cat ? (int) $cat['id'] : null,
        'description' => $note . $s['description'], 'materials_used' => $s['materials'], 'dimensions' => $s['dimensions'],
        'location' => $s['location'], 'completion_date' => $s['date'], 'is_featured' => $s['featured'],
        'meta_description' => mb_substr($s['description'], 0, 155),
    ]);
    $id = $project['id'];
    foreach ($s['images'] as $file => $alt) {
        $path = "$dir/$file";
        if (!is_file($path)) {
            echo "      missing image $file\n";
            continue;
        }
        $p = ImageProcessor::process($path);
        $paths = ImageStorage::store($p, "portfolio/$id");
        $images->create($id, [
            'image_path' => $paths['large'], 'medium_path' => $paths['medium'], 'thumbnail_path' => $paths['thumb'],
            'width' => $p['variants']['large']['width'], 'height' => $p['variants']['large']['height'], 'alt_text' => $alt,
        ]);
    }
    $images->syncFeatured($id);
    $service->setStatus($id, 'published');
    echo "added {$s['slug']} (" . count($s['images']) . " images)\n";
    $added++;
}
echo "done: $added added\n";
