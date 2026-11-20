<?php

namespace App\Services;

use App\Core\AppException;
use App\Core\ImageProcessor;
use App\Core\Logger;
use App\Core\Slug;
use App\Core\Validator;
use App\Repositories\CategoryRepository;
use App\Repositories\ImageRepository;
use App\Repositories\ProjectModelRepository;
use App\Repositories\ProjectRepository;

// Portfolio projects and their images: validation, slugs, publishing rules, file handling.
final class PortfolioService
{
    public const MAX_IMAGES_PER_PROJECT = 40;
    public const MAX_PER_PAGE = 48;

    private ProjectRepository $projects;
    private ImageRepository $images;
    private CategoryRepository $categories;

    public function __construct()
    {
        $this->projects = new ProjectRepository();
        $this->images = new ImageRepository();
        $this->categories = new CategoryRepository();
    }

    // ---- Listing ----------------------------------------------------------------------------

    /** Projects with their featured image, for list views. */
    public function summaries(array $rows, bool $public): array
    {
        $featured = $this->images->findMany(array_column($rows, 'featured_image_id'));
        $out = array_map(fn ($r) => Presenter::project($r, null, $public, $featured[(int) $r['featured_image_id']] ?? null), $rows);
        if (!$public) {
            // Admin lists flag projects that have a 3D model in the showroom.
            $models = (new ProjectModelRepository())->forProjects(array_column($rows, 'id'));
            foreach ($out as &$p) {
                $p['has_model'] = isset($models[$p['id']]);
            }
            unset($p);
        }
        return $out;
    }

    /**
     * Shared query parsing for the admin and public listings.
     * @return array{filters: array, sort: string, page: int, per_page: int}
     */
    public static function listQuery(array $q, bool $public, int $defaultPerPage): array
    {
        $v = Validator::make($q, [
            'page' => 'nullable|int|min:1|max:100000',
            'per_page' => 'nullable|int|min:1|max:' . self::MAX_PER_PAGE,
            'search' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:140',
            'sort' => 'nullable|in:' . implode(',', array_keys(ProjectRepository::SORTS)),
            'status' => 'nullable|in:draft,published',
            'featured' => 'nullable|bool',
        ]);
        $filters = ['search' => $v['search'] ?? null];
        if ($public) {
            $filters['status'] = 'published';
        } else {
            $filters['status'] = $v['status'] ?? null;
            if (isset($v['featured'])) {
                $filters['featured'] = $v['featured'];
            }
        }
        return [
            'filters' => $filters,
            'category' => $v['category'] ?? null,
            'sort' => $v['sort'] ?? 'order',
            'page' => $v['page'] ?? 1,
            'per_page' => $v['per_page'] ?? $defaultPerPage,
        ];
    }

    /** @param string|null $category category slug, or "none" (admin only) for uncategorised */
    public function list(array $query, bool $public): array
    {
        $filters = $query['filters'];
        if ($query['category'] !== null) {
            if (!$public && $query['category'] === 'none') {
                $filters['category_id'] = 0;
            } else {
                $cat = $this->categories->findBySlug($query['category']);
                if (!$cat || ($public && $cat['status'] !== 'active')) {
                    return $this->page([], 0, $query);
                }
                $filters['category_id'] = (int) $cat['id'];
            }
        }
        $res = $this->projects->paginate($filters, $query['sort'], $query['page'], $query['per_page']);
        return $this->page($this->summaries($res['items'], $public), $res['total'], $query);
    }

    private function page(array $items, int $total, array $query): array
    {
        return [
            'items' => $items,
            'pagination' => [
                'page' => $query['page'],
                'per_page' => $query['per_page'],
                'total' => $total,
                'total_pages' => (int) max(1, ceil($total / $query['per_page'])),
            ],
        ];
    }

    public function detail(int $id): array
    {
        $row = $this->projects->find($id) ?? throw new AppException('Project not found', 404);
        return Presenter::project($row, $this->images->forProject($id), false);
    }

    public function publicDetail(string $slug): array
    {
        $row = $this->projects->findPublishedBySlug($slug) ?? throw new AppException('Project not found', 404);
        $project = Presenter::project($row, $this->images->forProject((int) $row['id']), true);
        $project['related'] = $this->summaries($this->projects->related($row, 3), true);
        $project['model'] = (new ProjectModelService())->publicRef((int) $row['id']);
        return $project;
    }

    // ---- Create / update / delete -----------------------------------------------------------

    private function validated(array $body, bool $creating, ?int $id = null): array
    {
        $rules = [
            'title' => ($creating ? 'required' : 'nullable') . '|string|min:2|max:190',
            'slug' => 'nullable|string|max:140|slug',
            'category_id' => 'nullable|int|min:1',
            'description' => 'nullable|string|max:20000',
            'materials_used' => 'nullable|string|max:2000',
            'dimensions' => 'nullable|string|max:190',
            'location' => 'nullable|string|max:190',
            'completion_date' => 'nullable|date',
            'meta_title' => 'nullable|string|max:190',
            'meta_description' => 'nullable|string|max:320',
            'is_featured' => 'nullable|bool',
            'display_order' => 'nullable|int|min:0|max:1000000',
        ];
        // Only fields the client actually sent are validated and changed.
        $present = array_intersect_key($body, $rules);
        $d = Validator::make($present, array_intersect_key($rules, $present + ($creating ? ['title' => 1] : [])));
        if (!$creating && array_key_exists('title', $d) && $d['title'] === null) {
            throw new AppException('Validation failed', 422, ['title' => ['Title is required']]);
        }
        foreach (['description', 'materials_used', 'dimensions', 'location', 'meta_title', 'meta_description'] as $f) {
            if (isset($d[$f])) {
                $d[$f] = self::clean($d[$f]);
            }
        }
        if (isset($d['title'])) {
            $d['title'] = self::clean($d['title']);
        }
        if (!empty($d['category_id']) && !$this->categories->find($d['category_id'])) {
            throw new AppException('Validation failed', 422, ['category_id' => ['That category no longer exists']]);
        }
        if (array_key_exists('slug', $d) || $creating) {
            $explicit = $d['slug'] ?? null;
            $base = $explicit ?? Slug::from($d['title'] ?? 'project');
            if ($explicit !== null && $this->projects->slugTaken($explicit, $id)) {
                throw new AppException('Validation failed', 422, ['slug' => ['Another project already uses this URL']]);
            }
            $d['slug'] = $explicit ?? Slug::unique($base, fn ($s) => $this->projects->slugTaken($s, $id));
        }
        if (array_key_exists('slug', $d) && $d['slug'] === null) {
            unset($d['slug']);
        }
        if (array_key_exists('is_featured', $d)) {
            $d['is_featured'] = (bool) $d['is_featured'];
        }
        return $d;
    }

    /** Plain text only: tags are stripped (the frontend renders text, never HTML). */
    private static function clean(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = trim(strip_tags(str_replace("\0", '', $s)));
        return $s === '' ? null : $s;
    }

    public function create(array $body): array
    {
        $d = $this->validated($body, true);
        $d['status'] = 'draft'; // publishing is a separate, checked step
        $featured = !empty($d['is_featured']);
        unset($d['is_featured']); // set below so it also gets the next featured_order slot
        $id = $this->projects->create($d);
        if ($featured) {
            $this->projects->setFeatured($id, true);
        }
        Logger::info('portfolio', 'Project created', ['project_id' => $id]);
        return $this->detail($id);
    }

    public function update(int $id, array $body): array
    {
        $before = $this->projects->find($id) ?? throw new AppException('Project not found', 404);
        $d = $this->validated($body, false, $id);
        $featured = $d['is_featured'] ?? null;
        unset($d['is_featured']);
        $this->projects->update($id, $d);
        if ($featured !== null && $featured !== (bool) $before['is_featured']) {
            $this->projects->setFeatured($id, $featured);
        }
        if (isset($body['featured_image_id'])) {
            $this->images->syncFeatured($id, (int) $body['featured_image_id']);
        }
        if (array_key_exists('status', $body)) {
            $this->setStatus($id, (string) $body['status']);
        }
        return $this->detail($id);
    }

    public function setStatus(int $id, string $status): array
    {
        if (!in_array($status, ['draft', 'published'], true)) {
            throw new AppException('Validation failed', 422, ['status' => ['Status must be draft or published']]);
        }
        $row = $this->projects->find($id) ?? throw new AppException('Project not found', 404);
        if ($status === 'published' && (int) $row['image_count'] === 0) {
            throw new AppException('Add at least one image before publishing', 422, ['images' => ['A published project needs at least one image']]);
        }
        $this->projects->setStatus($id, $status);
        Logger::info('portfolio', $status === 'published' ? 'Project published' : 'Project unpublished', ['project_id' => $id]);
        return $this->detail($id);
    }

    public function setFeatured(int $id, bool $featured): array
    {
        $this->projects->find($id) ?? throw new AppException('Project not found', 404);
        $this->projects->setFeatured($id, $featured);
        return $this->detail($id);
    }

    public function delete(int $id): void
    {
        $this->projects->find($id) ?? throw new AppException('Project not found', 404);
        $files = [];
        foreach ($this->images->forProject($id) as $img) {
            array_push($files, $img['image_path'], $img['medium_path'], $img['thumbnail_path']);
        }
        $this->projects->delete($id); // image rows cascade
        ImageStorage::delete($files);
        ImageStorage::removeFolder("portfolio/$id");
        Logger::info('portfolio', 'Project deleted', ['project_id' => $id, 'files' => count($files)]);
    }

    /** @param int[] $ids */
    public function reorder(array $ids): void
    {
        $ids = self::idList($ids);
        if (count($this->projects->existingIds($ids)) !== count($ids)) {
            throw new AppException('Validation failed', 422, ['ids' => ['One or more projects no longer exist. Reload and try again.']]);
        }
        $this->projects->transaction(fn () => $this->projects->reorder($ids));
    }

    public function reorderFeatured(array $ids): array
    {
        $ids = self::idList($ids);
        $this->projects->transaction(fn () => $this->projects->setFeaturedOrder($ids));
        return $this->summaries($this->projects->allFeatured(), false);
    }

    public function featuredForAdmin(): array
    {
        return $this->summaries($this->projects->allFeatured(), false);
    }

    // ---- Images -----------------------------------------------------------------------------

    /** @param array[] $files normalised $_FILES entries */
    public function addImages(int $id, array $files, array $altTexts = []): array
    {
        $this->projects->find($id) ?? throw new AppException('Project not found', 404);
        if (!$files) {
            throw new AppException('Validation failed', 422, ['images' => ['Choose at least one image to upload']]);
        }
        $existing = count($this->images->forProject($id));
        if ($existing + count($files) > self::MAX_IMAGES_PER_PROJECT) {
            throw new AppException('Validation failed', 422, ['images' => ['A project can have at most ' . self::MAX_IMAGES_PER_PROJECT . ' images']]);
        }
        // Validate and process EVERY file before writing anything, so a bad file in a batch
        // leaves nothing half-saved.
        $processed = [];
        foreach ($files as $file) {
            $processed[] = ImageProcessor::fromUpload($file, 'images');
        }
        $written = [];
        try {
            $this->images->transaction(function () use ($id, $processed, $altTexts, &$written) {
                foreach ($processed as $i => $p) {
                    $paths = ImageStorage::store($p, "portfolio/$id");
                    $written = array_merge($written, array_values($paths));
                    $alt = self::clean(isset($altTexts[$i]) ? mb_substr((string) $altTexts[$i], 0, 255) : null);
                    $this->images->create($id, [
                        'image_path' => $paths['large'], 'medium_path' => $paths['medium'], 'thumbnail_path' => $paths['thumb'],
                        'width' => $p['variants']['large']['width'], 'height' => $p['variants']['large']['height'], 'alt_text' => $alt,
                        'focal' => $p['focal'],
                    ]);
                }
                $this->images->syncFeatured($id);
            });
        } catch (\Throwable $e) {
            ImageStorage::delete($written);
            throw $e;
        }
        Logger::info('portfolio', 'Images uploaded', ['project_id' => $id, 'count' => count($processed)]);
        return $this->detail($id);
    }

    public function updateImage(int $imageId, array $body): array
    {
        $img = $this->images->find($imageId) ?? throw new AppException('Image not found', 404);
        $projectId = (int) $img['project_id'];
        if (array_key_exists('alt_text', $body)) {
            $d = Validator::make($body, ['alt_text' => 'nullable|string|max:255']);
            $this->images->setAlt($imageId, self::clean($d['alt_text'] ?? null));
        }
        if (filter_var($body['is_featured'] ?? false, FILTER_VALIDATE_BOOL)) {
            $this->images->syncFeatured($projectId, $imageId);
        }
        // Focal point: { focal_x, focal_y } in 0..1 sets it by hand; { focal_reset: true } recomputes it.
        if (filter_var($body['focal_reset'] ?? false, FILTER_VALIDATE_BOOL)) {
            [$x, $y] = self::autoFocal($img);
            $this->images->setFocal($imageId, $x, $y, 'auto');
        } elseif (array_key_exists('focal_x', $body) || array_key_exists('focal_y', $body)) {
            $x = filter_var($body['focal_x'] ?? null, FILTER_VALIDATE_FLOAT);
            $y = filter_var($body['focal_y'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($x === false || $y === false || $x < 0 || $x > 1 || $y < 0 || $y > 1) {
                throw new AppException('Validation failed', 422, ['focal' => ['The focus point must be inside the image']]);
            }
            $this->images->setFocal($imageId, $x, $y, 'manual');
        }
        return $this->detail($projectId);
    }

    /** Recomputes an image's automatic focal point from its stored large variant. */
    public static function autoFocal(array $img): array
    {
        $path = ImageStorage::root() . '/' . $img['image_path'];
        $gd = is_file($path) ? @imagecreatefromwebp($path) : false;
        if (!$gd) {
            return [0.5, 0.5];
        }
        try {
            return ImageProcessor::focalPoint($gd);
        } finally {
            imagedestroy($gd);
        }
    }

    public function deleteImage(int $imageId): array
    {
        $img = $this->images->find($imageId) ?? throw new AppException('Image not found', 404);
        $projectId = (int) $img['project_id'];
        $project = $this->projects->find($projectId);
        if ($project && $project['status'] === 'published' && (int) $project['image_count'] <= 1) {
            throw new AppException('A published project needs at least one image. Unpublish it first, or add another image.', 422);
        }
        $this->images->transaction(function () use ($imageId, $projectId) {
            $this->images->delete($imageId);
            $this->images->syncFeatured($projectId);
        });
        ImageStorage::delete([$img['image_path'], $img['medium_path'], $img['thumbnail_path']]);
        return $this->detail($projectId);
    }

    public function reorderImages(int $id, array $ids): array
    {
        $this->projects->find($id) ?? throw new AppException('Project not found', 404);
        $ids = self::idList($ids);
        $current = array_map(fn ($r) => (int) $r['id'], $this->images->forProject($id));
        $a = $ids;
        $b = $current;
        sort($a);
        sort($b);
        if ($a !== $b) {
            throw new AppException('Validation failed', 422, ['ids' => ['Send every image of this project exactly once']]);
        }
        $this->images->transaction(fn () => $this->images->setOrder($ids));
        return $this->detail($id);
    }

    /** @return int[] */
    public static function idList(mixed $ids): array
    {
        if (!is_array($ids) || !$ids || count($ids) > 1000) {
            throw new AppException('Validation failed', 422, ['ids' => ['Send a list of ids']]);
        }
        $out = [];
        foreach ($ids as $v) {
            $n = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($n === false) {
                throw new AppException('Validation failed', 422, ['ids' => ['Ids must be positive whole numbers']]);
            }
            $out[] = $n;
        }
        if (count(array_unique($out)) !== count($out)) {
            throw new AppException('Validation failed', 422, ['ids' => ['Ids must not repeat']]);
        }
        return $out;
    }

    // ---- Public collections -----------------------------------------------------------------

    public function featured(int $limit): array
    {
        return $this->summaries($this->projects->featured($limit), true);
    }

    public function latest(int $limit): array
    {
        return $this->summaries($this->projects->latest($limit), true);
    }

    public function sitemapIndex(): array
    {
        return $this->projects->publishedIndex();
    }

    public function stats(): array
    {
        $s = array_map('intval', $this->projects->stats());
        $cats = $this->categories->all();
        $recent = $this->projects->paginate([], 'updated', 1, 5);
        return [
            'projects' => $s,
            'categories' => [
                'total' => count($cats),
                'active' => count(array_filter($cats, fn ($c) => $c['status'] === 'active')),
            ],
            'images' => $this->images->count(),
            'models' => (new ProjectModelRepository())->count(),
            'recent' => $this->summaries($recent['items'], false),
        ];
    }
}
