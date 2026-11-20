<?php

namespace App\Repositories;

class ProjectModelRepository extends BaseRepository
{
    public function forProject(int $projectId): ?array
    {
        return $this->run('SELECT * FROM portfolio_models WHERE project_id = ?', [$projectId])->fetch() ?: null;
    }

    /** @param int[] $projectIds @return array<int, array> rows keyed by project id */
    public function forProjects(array $projectIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $projectIds)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach ($this->run("SELECT * FROM portfolio_models WHERE project_id IN ($in)", $ids)->fetchAll() as $row) {
            $out[(int) $row['project_id']] = $row;
        }
        return $out;
    }

    public function save(int $projectId, array $d): void
    {
        $this->run(
            'INSERT INTO portfolio_models (project_id, template, template_version, params, default_finish, show_in_showroom)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE template = VALUES(template), template_version = VALUES(template_version),
                params = VALUES(params), default_finish = VALUES(default_finish), show_in_showroom = VALUES(show_in_showroom)',
            [$projectId, $d['template'], $d['template_version'], $d['params'], $d['default_finish'], $d['show_in_showroom'] ? 1 : 0]
        );
    }

    public function delete(int $projectId): bool
    {
        return $this->run('DELETE FROM portfolio_models WHERE project_id = ?', [$projectId])->rowCount() > 0;
    }

    /** Models of published projects shown in the showroom, with the project fields the catalogue needs. */
    public function showroom(): array
    {
        return $this->run(
            "SELECT m.*, p.slug AS project_slug, p.title AS project_title, p.description AS project_description,
                    p.featured_image_id, GREATEST(m.updated_at, p.updated_at) AS changed_at
             FROM portfolio_models m JOIN portfolio_projects p ON p.id = m.project_id
             WHERE p.status = 'published' AND m.show_in_showroom = 1
             ORDER BY p.display_order, p.id"
        )->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->run('SELECT COUNT(*) FROM portfolio_models')->fetchColumn();
    }
}
