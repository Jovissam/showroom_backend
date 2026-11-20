<?php

namespace App\Controllers;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\CategoryService;
use App\Services\PortfolioService;
use App\Services\ProjectModelService;
use App\Services\SettingsService;

// Public, read-only portfolio API. Only PUBLISHED projects and ACTIVE categories are exposed.
class PublicPortfolioController
{
    private function headers(): void
    {
        RateLimiter::hit('public:portfolio', 300, 60);
        // Short shared cache: publishing shows up within a minute, no deploy needed.
        header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
    }

    // GET /api/portfolio?page&per_page&category&search&sort
    public function index(Request $request, array $params): void
    {
        $this->headers();
        $settings = new SettingsService();
        $q = PortfolioService::listQuery(array_diff_key($_GET, ['status' => 1, 'featured' => 1]), true, $settings->int('portfolio_per_page'));
        if ($q['sort'] === 'updated') {
            $q['sort'] = 'order';
        }
        Response::success('Projects', (new PortfolioService())->list($q, true));
    }

    // GET /api/portfolio/{slug}  (includes images and related projects)
    public function show(Request $request, array $params): void
    {
        $this->headers();
        Response::success('Project', (new PortfolioService())->publicDetail((string) $params['slug']));
    }

    // GET /api/portfolio/categories
    public function categories(Request $request, array $params): void
    {
        $this->headers();
        Response::success('Categories', (new CategoryService())->list(true));
    }

    // GET /api/portfolio/featured?limit
    public function featured(Request $request, array $params): void
    {
        $this->headers();
        Response::success('Featured projects', (new PortfolioService())->featured($this->limit('homepage_featured_limit')));
    }

    // GET /api/portfolio/latest?limit
    public function latest(Request $request, array $params): void
    {
        $this->headers();
        Response::success('Latest projects', (new PortfolioService())->latest($this->limit('homepage_latest_limit')));
    }

    // GET /api/portfolio/settings: public showroom copy and limits.
    public function settings(Request $request, array $params): void
    {
        $this->headers();
        Response::success('Showroom settings', (new SettingsService())->all());
    }

    // GET /api/portfolio/sitemap: slugs and last-modified dates of published projects.
    public function sitemap(Request $request, array $params): void
    {
        $this->headers();
        Response::success('Sitemap', (new PortfolioService())->sitemapIndex());
    }

    // GET /api/showroom/models: 3D models of published projects (template + params + photos).
    public function showroomModels(Request $request, array $params): void
    {
        $this->headers();
        Response::success('Showroom models', (new ProjectModelService())->showroom());
    }

    private function limit(string $settingKey): int
    {
        $q = filter_var($_GET['limit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 24]]);
        return $q ?: max(1, (new SettingsService())->int($settingKey));
    }
}
