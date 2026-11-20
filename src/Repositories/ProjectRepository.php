<?php

namespace App\Repositories;

class ProjectRepository extends BaseRepository
{
    private const SELECT = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.status AS category_status,
        (SELECT COUNT(*) FROM portfolio_images i WHERE i.project_id = p.id) AS image_count
        FROM portfolio_projects p LEFT JOIN portfolio_categories c ON c.id = p.category_id';

    public const SORTS = [
        'order'   => 'p.display_order ASC, p.published_at DESC, p.id DESC',
        'newest'  => 'COALESCE(p.completion_date, DATE(p.published_at), DATE(p.created_at)) DESC, p.id DESC',
        'oldest'  => 'COALESCE(p.completion_date, DATE(p.published_at), DATE(p.created_at)) ASC, p.id ASC',
        'updated' => 'p.updated_at DESC, p.id DESC',
        'title'   => 'p.title ASC, p.id ASC',
    ];

    /**
     * Filtered, paginated listing.
     * @param array{status?: ?string, category_id?: ?int, search?: ?string, featured?: ?bool, active_category_only?: bool} $f
     * @return array{items: array, total: int}
     */
    public function paginate(array $f, string $sort, int $page, int $perPage): array
    {
        $where = [];
        $vals = [];
        if (!empty($f['status'])) {
            $where[] = 'p.status = ?';
            $vals[] = $f['status'];
        }
        if (array_key_exists('category_id', $f) && $f['category_id'] !== null) {
            if ($f['category_id'] === 0) {
                $where[] = 'p.category_id IS NULL';
            } else {
                $where[] = 'p.category_id = ?';
                $vals[] = $f['category_id'];
            }
        }
        if (!empty($f['search'])) {
            $where[] = 'p.title LIKE ?';
            $vals[] = self::like($f['search']);
        }
        if (isset($f['featured'])) {
            $where[] = 'p.is_featured = ?';
            $vals[] = $f['featured'] ? 1 : 0;
        }
        $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) $this->run('SELECT COUNT(*) FROM portfolio_projects p' . $sql, $vals)->fetchColumn();
        $order = self::SORTS[$sort] ?? self::SORTS['order'];
        $offset = max(0, ($page - 1) * $perPage);
        $items = $this->run(self::SELECT . $sql . " ORDER BY $order LIMIT $perPage OFFSET $offset", $vals)->fetchAll();
        return ['items' => $items, 'total' => $total];
    }

    public function find(int $id): ?array
    {
        return $this->run(self::SELECT . ' WHERE p.id = ?', [$id])->fetch() ?: null;
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->run(self::SELECT . " WHERE p.slug = ? AND p.status = 'published'", [$slug])->fetch() ?: null;
    }

    public function featured(int $limit): array
    {
        return $this->run(self::SELECT . " WHERE p.status = 'published' AND p.is_featured = 1 ORDER BY p.featured_order, p.display_order, p.id DESC LIMIT $limit")->fetchAll();
    }

    public function allFeatured(): array
    {
        return $this->run(self::SELECT . ' WHERE p.is_featured = 1 ORDER BY p.featured_order, p.display_order, p.id DESC')->fetchAll();
    }

    public function latest(int $limit): array
    {
        return $this->run(self::SELECT . " WHERE p.status = 'published' ORDER BY p.published_at DESC, p.id DESC LIMIT $limit")->fetchAll();
    }

    // Published projects in the same category first, then the newest others, never itself.
    public function related(array $project, int $limit): array
    {
        $rows = [];
        if ($project['category_id'] !== null) {
            $rows = $this->run(self::SELECT . " WHERE p.status = 'published' AND p.category_id = ? AND p.id <> ? ORDER BY p.display_order, p.published_at DESC LIMIT $limit",
                [$project['category_id'], $project['id']])->fetchAll();
        }
        if (count($rows) < $limit) {
            $exclude = array_merge([(int) $project['id']], array_map(fn ($r) => (int) $r['id'], $rows));
            $in = implode(',', array_fill(0, count($exclude), '?'));
            $more = $limit - count($rows);
            $rows = array_merge($rows, $this->run(self::SELECT . " WHERE p.status = 'published' AND p.id NOT IN ($in) ORDER BY p.published_at DESC LIMIT $more", $exclude)->fetchAll());
        }
        return $rows;
    }

    /** Slugs and dates of every published project (sitemap). */
    public function publishedIndex(): array
    {
        return $this->run("SELECT slug, updated_at FROM portfolio_projects WHERE status = 'published' ORDER BY published_at DESC")->fetchAll();
    }

    public function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        return (bool) $this->run('SELECT 1 FROM portfolio_projects WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0])->fetch();
    }

    private const COLUMNS = ['category_id', 'title', 'slug', 'description', 'materials_used', 'dimensions', 'location',
        'completion_date', 'is_featured', 'status', 'display_order', 'meta_title', 'meta_description'];

    public function create(array $d): int
    {
        $d['display_order'] ??= (int) $this->run('SELECT COALESCE(MAX(display_order), 0) + 1 FROM portfolio_projects')->fetchColumn();
        $cols = array_values(array_filter(self::COLUMNS, fn ($c) => array_key_exists($c, $d)));
        $this->run('INSERT INTO portfolio_projects (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            array_map(fn ($c) => is_bool($d[$c]) ? (int) $d[$c] : $d[$c], $cols));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $cols = array_values(array_filter(self::COLUMNS, fn ($c) => array_key_exists($c, $d)));
        if (!$cols) {
            return;
        }
        $vals = array_map(fn ($c) => is_bool($d[$c]) ? (int) $d[$c] : $d[$c], $cols);
        $vals[] = $id;
        $this->run('UPDATE portfolio_projects SET ' . implode(', ', array_map(fn ($c) => "$c = ?", $cols)) . ' WHERE id = ?', $vals);
    }

    public function setStatus(int $id, string $status): void
    {
        // published_at records the FIRST publication, so "latest" doesn't reshuffle on re-publish.
        $this->run("UPDATE portfolio_projects SET status = ?, published_at = IF(? = 'published', COALESCE(published_at, NOW()), published_at) WHERE id = ?", [$status, $status, $id]);
    }

    public function setFeatured(int $id, bool $featured): void
    {
        $next = (int) $this->run('SELECT COALESCE(MAX(featured_order), 0) + 1 FROM portfolio_projects WHERE is_featured = 1')->fetchColumn();
        // MySQL applies SET clauses left to right, so featured_order must read is_featured first.
        $this->run('UPDATE portfolio_projects SET featured_order = IF(? = 1 AND is_featured = 0, ?, featured_order), is_featured = ? WHERE id = ?',
            [$featured ? 1 : 0, $next, $featured ? 1 : 0, $id]);
    }

    public function delete(int $id): void
    {
        $this->run('DELETE FROM portfolio_projects WHERE id = ?', [$id]); // images cascade
    }

    public function existingIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        return array_map('intval', $this->run("SELECT id FROM portfolio_projects WHERE id IN ($in)", array_values($ids))->fetchAll(\PDO::FETCH_COLUMN));
    }

    // Moves the given projects into the given order while every other project keeps its place:
    // renumber everything 1..n by the current order, then hand the slots the listed projects
    // occupied (sorted) to them in the new order. Works for a filtered or paginated subset.
    public function reorder(array $ids): void
    {
        $all = array_map('intval', $this->run('SELECT p.id FROM portfolio_projects p ORDER BY ' . self::SORTS['order'])->fetchAll(\PDO::FETCH_COLUMN));
        $slots = [];
        foreach ($all as $i => $id) {
            if (in_array($id, $ids, true)) {
                $slots[] = $i;
            }
        }
        foreach (array_values($ids) as $n => $id) {
            $all[$slots[$n]] = $id;
        }
        $stmt = $this->db->prepare('UPDATE portfolio_projects SET display_order = ? WHERE id = ?');
        foreach ($all as $i => $id) {
            $stmt->execute([$i + 1, $id]);
        }
    }

    public function setFeaturedOrder(array $ids): void
    {
        $stmt = $this->db->prepare('UPDATE portfolio_projects SET featured_order = ? WHERE id = ? AND is_featured = 1');
        foreach (array_values($ids) as $i => $id) {
            $stmt->execute([$i + 1, $id]);
        }
    }

    public function stats(): array
    {
        return $this->run("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'published'), 0) AS published,
            COALESCE(SUM(status = 'draft'), 0) AS drafts, COALESCE(SUM(is_featured = 1), 0) AS featured,
            COALESCE(SUM(is_featured = 1 AND status = 'published'), 0) AS featured_published,
            COALESCE(SUM(category_id IS NULL), 0) AS uncategorised FROM portfolio_projects")->fetch();
    }
}
