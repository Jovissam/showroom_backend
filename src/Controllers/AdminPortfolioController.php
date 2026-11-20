<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\AdminAuth;
use App\Services\PortfolioService;
use App\Services\ProjectModelService;
use App\Services\SettingsService;

// /api/admin/portfolio…: every action requires a signed-in admin.
class AdminPortfolioController
{
    private PortfolioService $service;

    public function __construct()
    {
        $this->service = new PortfolioService();
    }

    private function auth(Request $request): void
    {
        AdminAuth::admin($request);
        header('Cache-Control: no-store');
    }

    // GET /api/admin/stats
    public function stats(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Dashboard', $this->service->stats());
    }

    // GET /api/admin/portfolio?page&per_page&search&category&status&featured&sort
    public function index(Request $request, array $params): void
    {
        $this->auth($request);
        $q = PortfolioService::listQuery($_GET, false, 20);
        Response::success('Projects', $this->service->list($q, false));
    }

    // GET /api/admin/portfolio/{id}  (drafts included: used by the editor and the preview)
    public function show(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Project', $this->service->detail((int) $params['id']));
    }

    // POST /api/admin/portfolio
    public function store(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Project created as a draft', $this->service->create($request->body()), 201);
    }

    // PUT /api/admin/portfolio/{id}
    public function update(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Project saved', $this->service->update((int) $params['id'], $request->body()));
    }

    // DELETE /api/admin/portfolio/{id}: removes the project, its image rows and files.
    public function destroy(Request $request, array $params): void
    {
        $this->auth($request);
        $this->service->delete((int) $params['id']);
        Response::success('Project deleted');
    }

    // PATCH /api/admin/portfolio/{id}/publish  { "status": "published" | "draft" }  (default: published)
    public function publish(Request $request, array $params): void
    {
        $this->auth($request);
        $status = (string) $request->input('status', 'published');
        $project = $this->service->setStatus((int) $params['id'], $status);
        Response::success($status === 'published' ? 'Project published' : 'Project moved to drafts', $project);
    }

    // PATCH /api/admin/portfolio/{id}/feature  { "is_featured": bool }  (default: true)
    public function feature(Request $request, array $params): void
    {
        $this->auth($request);
        $on = filter_var($request->input('is_featured', true), FILTER_VALIDATE_BOOL);
        Response::success($on ? 'Project featured' : 'Project removed from featured', $this->service->setFeatured((int) $params['id'], $on));
    }

    // PUT /api/admin/portfolio/reorder  { "ids": [...] }
    public function reorder(Request $request, array $params): void
    {
        $this->auth($request);
        $this->service->reorder($request->input('ids', []));
        Response::success('Order saved');
    }

    // GET /api/admin/portfolio/featured
    public function featured(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Featured projects', $this->service->featuredForAdmin());
    }

    // PUT /api/admin/portfolio/featured/reorder  { "ids": [...] }
    public function reorderFeatured(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Featured order saved', $this->service->reorderFeatured($request->input('ids', [])));
    }

    // POST /api/admin/portfolio/{id}/images  multipart: images[] (+ optional alt_text[])
    public function uploadImages(Request $request, array $params): void
    {
        $this->auth($request);
        $files = array_merge($request->files('images'), $request->files('image'));
        $alts = $request->input('alt_text', []);
        $project = $this->service->addImages((int) $params['id'], $files, is_array($alts) ? array_values($alts) : [$alts]);
        Response::success(count($files) === 1 ? 'Image uploaded' : count($files) . ' images uploaded', $project, 201);
    }

    // PUT /api/admin/portfolio/{id}/images/reorder  { "ids": [...] }
    public function reorderImages(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Image order saved', $this->service->reorderImages((int) $params['id'], $request->input('ids', [])));
    }

    // PUT /api/admin/portfolio/images/{imageId}  { "alt_text"?, "is_featured"? }
    public function updateImage(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Image saved', $this->service->updateImage((int) $params['imageId'], $request->body()));
    }

    // DELETE /api/admin/portfolio/images/{imageId}
    public function deleteImage(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Image removed', $this->service->deleteImage((int) $params['imageId']));
    }

    // GET /api/admin/portfolio/{id}/model  (null when the project has no 3D model)
    public function model(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('3D model', (new ProjectModelService())->get((int) $params['id']));
    }

    // PUT /api/admin/portfolio/{id}/model  { template, template_version, params, default_finish, show_in_showroom }
    public function saveModel(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('3D model saved', (new ProjectModelService())->save((int) $params['id'], $request->body()));
    }

    // DELETE /api/admin/portfolio/{id}/model
    public function deleteModel(Request $request, array $params): void
    {
        $this->auth($request);
        (new ProjectModelService())->delete((int) $params['id']);
        Response::success('3D model removed');
    }

    // GET /api/admin/settings
    public function settings(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Showroom settings', (new SettingsService())->all());
    }

    // PUT /api/admin/settings
    public function saveSettings(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Settings saved', (new SettingsService())->save($request->body()));
    }
}
