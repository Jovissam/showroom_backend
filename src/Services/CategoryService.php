<?php

namespace App\Services;

use App\Core\AppException;
use App\Core\ImageProcessor;
use App\Core\Logger;
use App\Core\Slug;
use App\Core\Validator;
use App\Repositories\CategoryRepository;

// Portfolio categories: managed in the admin dashboard, never hardcoded in the frontend.
final class CategoryService
{
    private CategoryRepository $repo;

    public function __construct()
    {
        $this->repo = new CategoryRepository();
    }

    public function list(bool $public): array
    {
        return array_map(fn ($c) => Presenter::category($c, $public), $this->repo->all($public));
    }

    private function get(int $id): array
    {
        return $this->repo->find($id) ?? throw new AppException('Category not found', 404);
    }

    private function validated(array $body, bool $creating, ?int $id = null): array
    {
        $rules = [
            'name' => ($creating ? 'required' : 'nullable') . '|string|min:2|max:120',
            'slug' => 'nullable|string|max:140|slug',
            'description' => 'nullable|string|max:2000',
            'display_order' => 'nullable|int|min:0|max:100000',
            'status' => 'nullable|in:active,inactive',
        ];
        $present = array_intersect_key($body, $rules);
        $d = Validator::make($present, array_intersect_key($rules, $present + ($creating ? ['name' => 1] : [])));
        if (array_key_exists('name', $d)) {
            $d['name'] = trim(strip_tags((string) $d['name']));
            if ($d['name'] === '') {
                throw new AppException('Validation failed', 422, ['name' => ['Name is required']]);
            }
            if ($this->repo->nameTaken($d['name'], $id)) {
                throw new AppException('Validation failed', 422, ['name' => ['A category with this name already exists']]);
            }
        }
        if (array_key_exists('description', $d) && $d['description'] !== null) {
            $d['description'] = trim(strip_tags($d['description'])) ?: null;
        }
        foreach (['display_order', 'status'] as $k) {
            if (array_key_exists($k, $d) && $d[$k] === null) {
                unset($d[$k]);
            }
        }
        if (!empty($d['slug'])) {
            if ($this->repo->slugTaken($d['slug'], $id)) {
                throw new AppException('Validation failed', 422, ['slug' => ['Another category already uses this URL']]);
            }
        } elseif ($creating) {
            $d['slug'] = Slug::unique(Slug::from($d['name']), fn ($s) => $this->repo->slugTaken($s));
        } else {
            unset($d['slug']);
        }
        return $d;
    }

    public function create(array $body): array
    {
        $id = $this->repo->create($this->validated($body, true));
        Logger::info('portfolio', 'Category created', ['category_id' => $id]);
        return Presenter::category($this->get($id));
    }

    public function update(int $id, array $body): array
    {
        $this->get($id);
        $this->repo->update($id, $this->validated($body, false, $id));
        return Presenter::category($this->get($id));
    }

    public function delete(int $id): void
    {
        $cat = $this->get($id);
        $this->repo->delete($id); // its projects become uncategorised (FK ON DELETE SET NULL)
        ImageStorage::delete([$cat['image']]);
        Logger::info('portfolio', 'Category deleted', ['category_id' => $id, 'projects_uncategorised' => (int) $cat['project_count']]);
    }

    public function setImage(int $id, ?array $file): array
    {
        $cat = $this->get($id);
        if (!$file) {
            throw new AppException('Validation failed', 422, ['image' => ['Choose an image to upload']]);
        }
        $paths = ImageStorage::store(ImageProcessor::fromUpload($file, 'image'), 'categories', ['medium']);
        $this->repo->update($id, ['image' => $paths['medium']]);
        ImageStorage::delete([$cat['image']]);
        return Presenter::category($this->get($id));
    }

    public function removeImage(int $id): array
    {
        $cat = $this->get($id);
        $this->repo->update($id, ['image' => null]);
        ImageStorage::delete([$cat['image']]);
        return Presenter::category($this->get($id));
    }

    public function reorder(array $ids): array
    {
        $ids = PortfolioService::idList($ids);
        $all = $this->repo->ids();
        $a = $ids;
        sort($a);
        sort($all);
        if ($a !== $all) {
            throw new AppException('Validation failed', 422, ['ids' => ['Send every category exactly once']]);
        }
        $this->repo->transaction(fn () => $this->repo->setOrder($ids));
        return $this->list(false);
    }
}
