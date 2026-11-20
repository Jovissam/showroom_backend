<?php

namespace App\Services;

use App\Core\AppException;
use App\Core\Logger;
use App\Repositories\ImageRepository;
use App\Repositories\ProjectModelRepository;
use App\Repositories\ProjectRepository;

// A project's 3D model for the showroom: a parametric template id plus measurements.
//
// The geometry is built by the frontend (app/three/builder/templates), which clamps every
// parameter to its template's ranges, so this layer only checks the SHAPE of the data: a known
// template, a small flat object of scalar values, and a plausible finish id. Nothing stored here
// is ever executed or rendered as HTML.
final class ProjectModelService
{
    public const TEMPLATES = ['wardrobe', 'kitchen', 'tv-wall', 'shelving'];
    private const MAX_PARAMS = 20;
    private const MAX_JSON = 2048;

    private ProjectModelRepository $models;
    private ProjectRepository $projects;

    public function __construct()
    {
        $this->models = new ProjectModelRepository();
        $this->projects = new ProjectRepository();
    }

    public static function present(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $params = json_decode((string) $row['params'], true);
        return [
            'template' => $row['template'],
            'template_version' => (int) $row['template_version'],
            'params' => is_array($params) ? $params : new \stdClass(),
            'default_finish' => $row['default_finish'],
            'show_in_showroom' => (bool) $row['show_in_showroom'],
            'updated_at' => $row['updated_at'],
        ];
    }

    public function get(int $projectId): ?array
    {
        $this->projects->find($projectId) ?? throw new AppException('Project not found', 404);
        return self::present($this->models->forProject($projectId));
    }

    public function save(int $projectId, array $body): array
    {
        $this->projects->find($projectId) ?? throw new AppException('Project not found', 404);
        $errors = [];

        $template = $body['template'] ?? null;
        if (!is_string($template) || !in_array($template, self::TEMPLATES, true)) {
            $errors['template'][] = 'Choose a template: ' . implode(', ', self::TEMPLATES);
        }
        $version = filter_var($body['template_version'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($version === false) {
            $errors['template_version'][] = 'Template version must be a whole number';
        }

        $params = $body['params'] ?? [];
        if (!is_array($params) || ($params !== [] && array_is_list($params))) {
            $errors['params'][] = 'Measurements must be an object of named values';
            $params = [];
        } elseif (count($params) > self::MAX_PARAMS) {
            $errors['params'][] = 'Too many measurements';
        } else {
            foreach ($params as $k => $v) {
                $okKey = is_string($k) && preg_match('/^[a-zA-Z][a-zA-Z0-9]{0,31}$/', $k);
                $okValue = is_bool($v) || is_int($v) || (is_float($v) && is_finite($v)) || (is_string($v) && mb_strlen($v) <= 32 && preg_match('/^[a-z0-9-]*$/', $v));
                if (!$okKey || !$okValue) {
                    $errors['params'][] = 'Invalid measurement: ' . mb_substr((string) $k, 0, 32);
                    break;
                }
            }
        }
        $json = json_encode((object) $params, JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) > self::MAX_JSON) {
            $errors['params'][] = 'Measurements are too large';
        }

        $finish = $body['default_finish'] ?? null;
        if ($finish !== null && $finish !== '' && (!is_string($finish) || !preg_match('/^[a-z0-9-]{1,64}$/', $finish))) {
            $errors['default_finish'][] = 'Unknown finish';
        }
        $show = filter_var($body['show_in_showroom'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($show === null) {
            $errors['show_in_showroom'][] = 'Show in showroom must be true or false';
        }
        if ($errors) {
            throw new AppException('Validation failed', 422, $errors);
        }

        $this->models->save($projectId, [
            'template' => $template, 'template_version' => $version, 'params' => $json,
            'default_finish' => $finish ?: null, 'show_in_showroom' => $show,
        ]);
        Logger::info('portfolio', '3D model saved', ['project_id' => $projectId, 'template' => $template]);
        return self::present($this->models->forProject($projectId));
    }

    public function delete(int $projectId): void
    {
        $this->projects->find($projectId) ?? throw new AppException('Project not found', 404);
        if (!$this->models->delete($projectId)) {
            throw new AppException('This project has no 3D model', 404);
        }
        Logger::info('portfolio', '3D model removed', ['project_id' => $projectId]);
    }

    /** Public: models of published projects, with what the catalogue shows (photos, copy). */
    public function showroom(): array
    {
        $rows = $this->models->showroom();
        $images = (new ImageRepository())->forProjects(array_map(fn ($r) => (int) $r['project_id'], $rows));
        return array_map(function (array $r) use ($images) {
            $imgs = $images[(int) $r['project_id']] ?? [];
            // Featured photo first (it becomes the catalogue cover), then gallery order.
            usort($imgs, fn ($a, $b) => [(int) !$a['is_featured'], (int) $a['display_order']] <=> [(int) !$b['is_featured'], (int) $b['display_order']]);
            $model = self::present($r);
            $model['updated_at'] = $r['changed_at'];
            return [
                'project_id' => (int) $r['project_id'],
                'project_slug' => $r['project_slug'],
                'project_title' => $r['project_title'],
                'description' => $r['project_description'],
                'images' => array_map(fn ($i) => array_intersect_key(Presenter::image($i), array_flip(['url', 'medium_url', 'thumbnail_url', 'alt_text', 'width', 'height', 'focal_x', 'focal_y'])), array_slice($imgs, 0, 8)),
            ] + $model;
        }, $rows);
    }

    /** Public project page: does this project have a model in the showroom? */
    public function publicRef(int $projectId): ?array
    {
        $row = $this->models->forProject($projectId);
        if (!$row || !(int) $row['show_in_showroom']) {
            return null;
        }
        $project = $this->projects->find($projectId);
        return ['template' => $row['template'], 'furniture_slug' => $project['slug'] ?? null];
    }
}
