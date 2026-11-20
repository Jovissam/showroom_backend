<?php

use App\Controllers\AdminAuthController;
use App\Controllers\AdminCategoryController;
use App\Controllers\AdminPortfolioController;
use App\Controllers\PublicPortfolioController;
use App\Core\Router;

// Jovisco API. Handler format: [Controller::class, 'method'].
// {id} / {imageId} only match digits (see Router), so fixed segments such as
// /portfolio/categories never collide with /portfolio/{id}. Public {slug} must stay last.
return function (Router $r): void {
    // ---- Admin authentication (login is the only unauthenticated admin route) -----------
    $r->post('/api/admin/auth/login',  [AdminAuthController::class, 'login']);
    $r->get('/api/admin/auth/me',      [AdminAuthController::class, 'me']);
    $r->post('/api/admin/auth/logout', [AdminAuthController::class, 'logout']);

    // ---- Admin dashboard & showroom settings ---------------------------------------------
    $r->get('/api/admin/stats',    [AdminPortfolioController::class, 'stats']);
    $r->get('/api/admin/settings', [AdminPortfolioController::class, 'settings']);
    $r->put('/api/admin/settings', [AdminPortfolioController::class, 'saveSettings']);

    // ---- Admin categories ----------------------------------------------------------------
    $r->get('/api/admin/portfolio/categories',               [AdminCategoryController::class, 'index']);
    $r->post('/api/admin/portfolio/categories',              [AdminCategoryController::class, 'store']);
    $r->put('/api/admin/portfolio/categories/reorder',       [AdminCategoryController::class, 'reorder']);
    $r->put('/api/admin/portfolio/categories/{id}',          [AdminCategoryController::class, 'update']);
    $r->delete('/api/admin/portfolio/categories/{id}',       [AdminCategoryController::class, 'destroy']);
    $r->post('/api/admin/portfolio/categories/{id}/image',   [AdminCategoryController::class, 'uploadImage']);
    $r->delete('/api/admin/portfolio/categories/{id}/image', [AdminCategoryController::class, 'deleteImage']);

    // ---- Admin portfolio projects --------------------------------------------------------
    $r->get('/api/admin/portfolio',                     [AdminPortfolioController::class, 'index']);
    $r->post('/api/admin/portfolio',                    [AdminPortfolioController::class, 'store']);
    $r->put('/api/admin/portfolio/reorder',             [AdminPortfolioController::class, 'reorder']);
    $r->get('/api/admin/portfolio/featured',            [AdminPortfolioController::class, 'featured']);
    $r->put('/api/admin/portfolio/featured/reorder',    [AdminPortfolioController::class, 'reorderFeatured']);
    $r->put('/api/admin/portfolio/images/{imageId}',    [AdminPortfolioController::class, 'updateImage']);
    $r->delete('/api/admin/portfolio/images/{imageId}', [AdminPortfolioController::class, 'deleteImage']);
    $r->get('/api/admin/portfolio/{id}',                [AdminPortfolioController::class, 'show']);
    $r->put('/api/admin/portfolio/{id}',                [AdminPortfolioController::class, 'update']);
    $r->delete('/api/admin/portfolio/{id}',             [AdminPortfolioController::class, 'destroy']);
    $r->patch('/api/admin/portfolio/{id}/publish',      [AdminPortfolioController::class, 'publish']);
    $r->patch('/api/admin/portfolio/{id}/feature',      [AdminPortfolioController::class, 'feature']);
    $r->post('/api/admin/portfolio/{id}/images',        [AdminPortfolioController::class, 'uploadImages']);
    $r->put('/api/admin/portfolio/{id}/images/reorder', [AdminPortfolioController::class, 'reorderImages']);
    // 3D model for the showroom (template + measurements)
    $r->get('/api/admin/portfolio/{id}/model',           [AdminPortfolioController::class, 'model']);
    $r->put('/api/admin/portfolio/{id}/model',           [AdminPortfolioController::class, 'saveModel']);
    $r->delete('/api/admin/portfolio/{id}/model',        [AdminPortfolioController::class, 'deleteModel']);

    // ---- Public portfolio (published content and active categories only) ----------------
    $r->get('/api/portfolio',            [PublicPortfolioController::class, 'index']);
    $r->get('/api/portfolio/categories', [PublicPortfolioController::class, 'categories']);
    $r->get('/api/portfolio/featured',   [PublicPortfolioController::class, 'featured']);
    $r->get('/api/portfolio/latest',     [PublicPortfolioController::class, 'latest']);
    $r->get('/api/portfolio/settings',   [PublicPortfolioController::class, 'settings']);
    $r->get('/api/portfolio/sitemap',    [PublicPortfolioController::class, 'sitemap']);
    $r->get('/api/portfolio/{slug}',     [PublicPortfolioController::class, 'show']);

    // ---- Public showroom: 3D models generated from published projects --------------------
    $r->get('/api/showroom/models', [PublicPortfolioController::class, 'showroomModels']);
};
