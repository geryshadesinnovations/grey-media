<?php
/**
 * Greyshades Innovations - Route Definitions
 *
 * @var \App\Core\Router $router
 */
declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DownloadRequestController;
use App\Controllers\FavoritesController;
use App\Controllers\FollowController;
use App\Controllers\MediaController;
use App\Controllers\NotificationController;
use App\Controllers\SearchController;
use App\Controllers\ShareController;
use App\Controllers\SharePublicController;
use App\Controllers\StreamController;
use App\Controllers\UploadController;
use App\Core\Middleware\RequireAdmin;
use App\Core\Middleware\RequireAuth;

// Public
$router->get('/',         fn () => redirect('/dashboard'));
$router->get('/login',    [AuthController::class, 'showLogin']);
$router->post('/login',   [AuthController::class, 'login']);
$router->post('/logout',  [AuthController::class, 'logout'], [RequireAuth::class]);

// Public share links (no login / no password - token-gated, auto-expiring)
$router->get('/s/{token}',          [SharePublicController::class, 'show']);
$router->get('/s/{token}/stream',   [SharePublicController::class, 'stream']);
$router->get('/s/{token}/thumb',    [SharePublicController::class, 'thumb']);
$router->get('/s/{token}/preview',  [SharePublicController::class, 'preview']);

// Authenticated dashboard / media browsing
$router->get('/dashboard',         [DashboardController::class, 'index'],   [RequireAuth::class]);
$router->get('/favorites',         [FavoritesController::class, 'index'],   [RequireAuth::class]);
$router->post('/favorites/toggle/{uuid}', [FavoritesController::class, 'toggle'], [RequireAuth::class]);
$router->post('/categories/{id}/follow', [FollowController::class, 'toggle'], [RequireAuth::class]);
$router->get('/search/suggest',    [SearchController::class, 'suggest'],    [RequireAuth::class]);

// Notifications
$router->get('/notifications',          [NotificationController::class, 'index'],   [RequireAuth::class]);
$router->get('/notifications/feed',     [NotificationController::class, 'feed'],    [RequireAuth::class]);
$router->post('/notifications/read-all',[NotificationController::class, 'readAll'], [RequireAuth::class]);
$router->post('/notifications/{id}/read',[NotificationController::class, 'read'],   [RequireAuth::class]);

$router->get('/media/{uuid}',      [MediaController::class, 'show'],        [RequireAuth::class]);
$router->get('/media/{id}/edit',   [MediaController::class, 'edit'],        [RequireAuth::class]);
$router->post('/media/{id}',       [MediaController::class, 'update'],      [RequireAuth::class]);
$router->post('/media/{id}/delete',[MediaController::class, 'destroy'],     [RequireAuth::class]);
$router->post('/media/{uuid}/share',            [ShareController::class, 'create'],          [RequireAuth::class]);
$router->post('/media/{uuid}/download-request', [DownloadRequestController::class, 'store'], [RequireAuth::class]);
$router->post('/share/{id}/revoke',             [ShareController::class, 'revoke'],          [RequireAuth::class]);

// Upload
$router->get('/upload',  [UploadController::class, 'showForm'],  [RequireAuth::class]);
$router->post('/upload', [UploadController::class, 'store'],     [RequireAuth::class]);

// Streaming / preview / download (token-protected internally)
$router->get('/stream/{uuid}',           [StreamController::class, 'stream'],   [RequireAuth::class]);
$router->get('/stream/{uuid}/hls/{seg}', [StreamController::class, 'hls'],      [RequireAuth::class]);
$router->get('/thumb/{uuid}',            [StreamController::class, 'thumb'],    [RequireAuth::class]);
$router->get('/preview/{uuid}',          [StreamController::class, 'preview'],  [RequireAuth::class]);
$router->get('/download/approved/{token}',[DownloadRequestController::class, 'downloadApproved'], [RequireAuth::class]);
$router->get('/download/{uuid}',         [StreamController::class, 'download'], [RequireAuth::class]);

// Admin
$router->get('/admin',                       [AdminController::class, 'dashboard'],     [RequireAdmin::class]);
$router->get('/admin/users',                 [AdminController::class, 'users'],         [RequireAdmin::class]);
$router->post('/admin/users',                [AdminController::class, 'userStore'],     [RequireAdmin::class]);
$router->post('/admin/users/{id}',           [AdminController::class, 'userUpdate'],    [RequireAdmin::class]);
$router->post('/admin/users/{id}/delete',    [AdminController::class, 'userDelete'],    [RequireAdmin::class]);
$router->get('/admin/categories',            [AdminController::class, 'categories'],    [RequireAdmin::class]);
$router->post('/admin/categories',           [AdminController::class, 'categoryStore'], [RequireAdmin::class]);
$router->post('/admin/categories/{id}/delete',[AdminController::class, 'categoryDelete'],[RequireAdmin::class]);
$router->get('/admin/companies',              [AdminController::class, 'companies'],     [RequireAdmin::class]);
$router->post('/admin/companies',             [AdminController::class, 'companyStore'],  [RequireAdmin::class]);
$router->post('/admin/companies/{id}/delete', [AdminController::class, 'companyDelete'], [RequireAdmin::class]);
$router->get('/admin/activity',              [AdminController::class, 'activity'],      [RequireAdmin::class]);
$router->get('/admin/download-requests',     [AdminController::class, 'downloadRequests'], [RequireAdmin::class]);
$router->post('/admin/download-requests/{id}/approve', [AdminController::class, 'downloadRequestApprove'], [RequireAdmin::class]);
$router->post('/admin/download-requests/{id}/reject',  [AdminController::class, 'downloadRequestReject'],  [RequireAdmin::class]);
$router->get('/admin/analytics',             [AdminController::class, 'analytics'],     [RequireAdmin::class]);
$router->get('/admin/analytics/export',      [AdminController::class, 'analyticsExport'],[RequireAdmin::class]);
