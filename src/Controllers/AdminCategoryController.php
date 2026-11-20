<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\AdminAuth;
use App\Services\CategoryService;

// /api/admin/portfolio/categories…: every action requires a signed-in admin.
class AdminCategoryController
{
    private CategoryService $service;

    public function __construct()
    {
        $this->service = new CategoryService();
    }

    private function auth(Request $request): void
    {
        AdminAuth::admin($request);
        header('Cache-Control: no-store');
    }

    public function index(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Categories', $this->service->list(false));
    }

    public function store(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Category created', $this->service->create($request->body()), 201);
    }

    public function update(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Category saved', $this->service->update((int) $params['id'], $request->body()));
    }

    // Projects in a deleted category are kept and become uncategorised.
    public function destroy(Request $request, array $params): void
    {
        $this->auth($request);
        $this->service->delete((int) $params['id']);
        Response::success('Category deleted. Its projects are kept, now without a category.');
    }

    // PUT /api/admin/portfolio/categories/reorder  { "ids": [...] }
    public function reorder(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Category order saved', $this->service->reorder($request->input('ids', [])));
    }

    // POST /api/admin/portfolio/categories/{id}/image  multipart: image
    public function uploadImage(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Category image saved', $this->service->setImage((int) $params['id'], $request->files('image')[0] ?? null));
    }

    // DELETE /api/admin/portfolio/categories/{id}/image
    public function deleteImage(Request $request, array $params): void
    {
        $this->auth($request);
        Response::success('Category image removed', $this->service->removeImage((int) $params['id']));
    }
}
