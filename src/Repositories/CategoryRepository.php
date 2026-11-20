<?php

namespace App\Repositories;

class CategoryRepository extends BaseRepository
{
    private const COUNTS = '(SELECT COUNT(*) FROM portfolio_projects p WHERE p.category_id = c.id) AS project_count,
        (SELECT COUNT(*) FROM portfolio_projects p WHERE p.category_id = c.id AND p.status = \'published\') AS published_count';

    public function all(bool $activeOnly = false): array
    {
        $where = $activeOnly ? "WHERE c.status = 'active'" : '';
        return $this->run('SELECT c.*, ' . self::COUNTS . " FROM portfolio_categories c $where ORDER BY c.display_order, c.name")->fetchAll();
    }

    public function find(int $id): ?array
    {
        return $this->run('SELECT c.*, ' . self::COUNTS . ' FROM portfolio_categories c WHERE c.id = ?', [$id])->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->run('SELECT * FROM portfolio_categories WHERE slug = ?', [$slug])->fetch() ?: null;
    }

    public function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        return (bool) $this->run('SELECT 1 FROM portfolio_categories WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0])->fetch();
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        return (bool) $this->run('SELECT 1 FROM portfolio_categories WHERE name = ? AND id <> ?', [$name, $exceptId ?? 0])->fetch();
    }

    public function create(array $d): int
    {
        $order = (int) $this->run('SELECT COALESCE(MAX(display_order), 0) + 1 FROM portfolio_categories')->fetchColumn();
        $this->run('INSERT INTO portfolio_categories (name, slug, description, display_order, status) VALUES (?, ?, ?, ?, ?)', [
            $d['name'], $d['slug'], $d['description'] ?? null, $d['display_order'] ?? $order, $d['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $allowed = ['name', 'slug', 'description', 'display_order', 'status', 'image'];
        $sets = [];
        $vals = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $d)) {
                $sets[] = "$col = ?";
                $vals[] = $d[$col];
            }
        }
        if ($sets) {
            $vals[] = $id;
            $this->run('UPDATE portfolio_categories SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
        }
    }

    public function delete(int $id): void
    {
        // portfolio_projects.category_id is ON DELETE SET NULL: the projects stay.
        $this->run('DELETE FROM portfolio_categories WHERE id = ?', [$id]);
    }

    public function ids(): array
    {
        return array_map('intval', $this->run('SELECT id FROM portfolio_categories')->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function setOrder(array $ids): void
    {
        $stmt = $this->db->prepare('UPDATE portfolio_categories SET display_order = ? WHERE id = ?');
        foreach (array_values($ids) as $i => $id) {
            $stmt->execute([$i + 1, $id]);
        }
    }
}
