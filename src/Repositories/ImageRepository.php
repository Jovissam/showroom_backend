<?php

namespace App\Repositories;

class ImageRepository extends BaseRepository
{
    public function forProject(int $projectId): array
    {
        return $this->run('SELECT * FROM portfolio_images WHERE project_id = ? ORDER BY display_order, id', [$projectId])->fetchAll();
    }

    /** @param int[] $projectIds @return array<int, array> rows grouped by project id */
    public function forProjects(array $projectIds): array
    {
        if (!$projectIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($projectIds), '?'));
        $out = [];
        foreach ($this->run("SELECT * FROM portfolio_images WHERE project_id IN ($in) ORDER BY display_order, id", array_values($projectIds))->fetchAll() as $row) {
            $out[(int) $row['project_id']][] = $row;
        }
        return $out;
    }

    /** @param int[] $ids @return array<int, array> rows keyed by image id */
    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach ($this->run("SELECT * FROM portfolio_images WHERE id IN ($in)", $ids)->fetchAll() as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    public function find(int $id): ?array
    {
        return $this->run('SELECT * FROM portfolio_images WHERE id = ?', [$id])->fetch() ?: null;
    }

    public function create(int $projectId, array $d): int
    {
        $order = (int) $this->run('SELECT COALESCE(MAX(display_order), 0) + 1 FROM portfolio_images WHERE project_id = ?', [$projectId])->fetchColumn();
        $this->run('INSERT INTO portfolio_images (project_id, image_path, medium_path, thumbnail_path, width, height, focal_x, focal_y, alt_text, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $projectId, $d['image_path'], $d['medium_path'], $d['thumbnail_path'], $d['width'], $d['height'],
            $d['focal'][0] ?? 0.5, $d['focal'][1] ?? 0.5, $d['alt_text'] ?? null, $order,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Sets the focal point; `manual` points are never overwritten by the automatic backfill. */
    public function setFocal(int $id, float $x, float $y, string $source): void
    {
        $this->run('UPDATE portfolio_images SET focal_x = ?, focal_y = ?, focal_source = ? WHERE id = ?', [round($x, 3), round($y, 3), $source, $id]);
    }

    public function setAlt(int $id, ?string $alt): void
    {
        $this->run('UPDATE portfolio_images SET alt_text = ? WHERE id = ?', [$alt, $id]);
    }

    public function delete(int $id): void
    {
        $this->run('DELETE FROM portfolio_images WHERE id = ?', [$id]);
    }

    public function setOrder(array $ids): void
    {
        $stmt = $this->db->prepare('UPDATE portfolio_images SET display_order = ? WHERE id = ?');
        foreach (array_values($ids) as $i => $id) {
            $stmt->execute([$i + 1, $id]);
        }
    }

    // Exactly one featured image per project (or none when it has no images): the requested
    // one if it belongs to the project, else the current one, else the first in order.
    public function syncFeatured(int $projectId, ?int $preferId = null): ?int
    {
        $rows = $this->forProject($projectId);
        $ids = array_map(fn ($r) => (int) $r['id'], $rows);
        $current = null;
        foreach ($rows as $r) {
            if ((int) $r['is_featured'] === 1) {
                $current = (int) $r['id'];
                break;
            }
        }
        $chosen = ($preferId !== null && in_array($preferId, $ids, true)) ? $preferId : ($current ?? ($ids[0] ?? null));
        $this->run('UPDATE portfolio_images SET is_featured = (id = ?) WHERE project_id = ?', [$chosen ?? 0, $projectId]);
        $this->run('UPDATE portfolio_projects SET featured_image_id = ? WHERE id = ?', [$chosen, $projectId]);
        return $chosen;
    }

    public function count(): int
    {
        return (int) $this->run('SELECT COUNT(*) FROM portfolio_images')->fetchColumn();
    }
}
