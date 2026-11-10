<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\BookController;
use App\Controllers\CatalogController;
use App\Controllers\FineController;
use App\Controllers\IssueController;
use App\Controllers\MeController;
use App\Controllers\ReportController;
use App\Controllers\ReservationController;
use App\Controllers\StudentController;
use App\Helpers\Database;
use App\Helpers\Http;
use App\Helpers\RequestContext;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RoleMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

// Route table. Middleware added last runs first, so each group adds the role check before authentication.

return static function (App $app): void {
    $container = $app->getContainer();
    assert($container instanceof ContainerInterface);
    $context = $container->get(RequestContext::class);
    $auth = $container->get(AuthMiddleware::class);
    $roles = static fn (string ...$roles): RoleMiddleware => new RoleMiddleware($context, array_values($roles));
    $limit = static fn (string $name, int $max): RateLimitMiddleware
        => new RateLimitMiddleware($container->get(Database::class), $context, $name, $max);

    $app->group('/api/v1', static function (RouteCollectorProxy $api) use ($auth, $roles, $limit): void {
        $api->get('/health', static function (ResponseInterface $response, Database $db): ResponseInterface {
            $db->scalar('SELECT 1');

            return Http::json($response, ['status' => 'ok', 'time' => gmdate(DATE_ATOM)]);
        });

        // Authentication
        $api->post('/auth/login', [AuthController::class, 'login'])->add($limit('login', 5));
        $api->post('/auth/refresh', [AuthController::class, 'refresh'])->add($limit('refresh', 30));
        $api->post('/auth/logout', [AuthController::class, 'logout']);
        $api->post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->add($limit('forgot', 5));
        $api->post('/auth/reset-password', [AuthController::class, 'resetPassword'])->add($limit('reset', 10));

        // Public catalogue
        $api->get('/books', [BookController::class, 'index']);
        $api->get('/books/{id:[0-9]+}', [BookController::class, 'show']);
        $api->get('/categories', [CatalogController::class, 'categories']);
        $api->get('/authors', [CatalogController::class, 'authors']);

        // Any signed-in user
        $api->group('', static function (RouteCollectorProxy $r): void {
            $r->get('/auth/me', [AuthController::class, 'me']);
            $r->put('/auth/change-password', [AuthController::class, 'changePassword']);
            $r->put('/me/profile', [MeController::class, 'updateProfile']);
            $r->get('/me/notifications', [MeController::class, 'notifications']);
            $r->post('/me/notifications/read-all', [MeController::class, 'readAllNotifications']);
            $r->post('/me/notifications/{id:[0-9]+}/read', [MeController::class, 'readNotification']);
            // Students may renew/cancel their own items; the service enforces ownership.
            $r->post('/issues/{id:[0-9]+}/renew', [IssueController::class, 'renew']);
            $r->delete('/reservations/{id:[0-9]+}', [ReservationController::class, 'destroy']);
        })->add($auth);

        // Students
        $api->group('', static function (RouteCollectorProxy $r): void {
            $r->get('/me/dashboard', [MeController::class, 'dashboard']);
            $r->get('/me/issues', [MeController::class, 'issues']);
            $r->get('/me/reservations', [MeController::class, 'reservations']);
            $r->get('/me/fines', [MeController::class, 'fines']);
            $r->post('/reservations', [ReservationController::class, 'store']);
        })->add($roles('student'))->add($auth);

        // Staff (admin + librarian)
        $api->group('', static function (RouteCollectorProxy $r): void {
            $r->post('/books', [BookController::class, 'store']);
            $r->post('/books/import', [BookController::class, 'import']);
            $r->put('/books/{id:[0-9]+}', [BookController::class, 'update']);
            $r->get('/books/{id:[0-9]+}/copies', [BookController::class, 'copies']);
            $r->post('/books/{id:[0-9]+}/copies', [BookController::class, 'addCopies']);
            $r->post('/books/{id:[0-9]+}/cover', [BookController::class, 'uploadCover']);
            $r->delete('/books/{id:[0-9]+}/cover', [BookController::class, 'removeCover']);
            $r->get('/copies/{barcode}', [BookController::class, 'copyByBarcode']);
            $r->get('/isbn-lookup/{isbn}', [BookController::class, 'lookupIsbn']);

            $r->post('/categories', [CatalogController::class, 'createCategory']);
            $r->put('/categories/{id:[0-9]+}', [CatalogController::class, 'updateCategory']);
            $r->post('/authors', [CatalogController::class, 'createAuthor']);
            $r->put('/authors/{id:[0-9]+}', [CatalogController::class, 'updateAuthor']);

            $r->get('/students', [StudentController::class, 'index']);
            $r->post('/students', [StudentController::class, 'store']);
            $r->post('/students/import', [StudentController::class, 'import']);
            $r->get('/students/by-roll/{rollNo}', [StudentController::class, 'byRollNo']);
            $r->get('/students/{id:[0-9]+}', [StudentController::class, 'show']);
            $r->put('/students/{id:[0-9]+}', [StudentController::class, 'update']);
            $r->post('/students/{id:[0-9]+}/suspend', [StudentController::class, 'suspend']);
            $r->post('/students/{id:[0-9]+}/activate', [StudentController::class, 'activate']);
            $r->get('/students/{id:[0-9]+}/history', [StudentController::class, 'history']);

            $r->get('/issues', [IssueController::class, 'index']);
            $r->post('/issues', [IssueController::class, 'store']);
            $r->get('/issues/{id:[0-9]+}', [IssueController::class, 'show']);
            $r->get('/issues/{id:[0-9]+}/fine-preview', [IssueController::class, 'finePreview']);
            $r->post('/issues/{id:[0-9]+}/return', [IssueController::class, 'returnBook']);
            $r->post('/issues/{id:[0-9]+}/mark-lost', [IssueController::class, 'markLost']);
            $r->post('/issues/{id:[0-9]+}/remind', [IssueController::class, 'remind']);

            $r->get('/reservations', [ReservationController::class, 'index']);
            $r->post('/reservations/{id:[0-9]+}/fulfil', [ReservationController::class, 'fulfil']);

            $r->get('/fines', [FineController::class, 'index']);
            $r->post('/fines/{id:[0-9]+}/pay', [FineController::class, 'pay']);

            $r->get('/reports/summary', [ReportController::class, 'summary']);
            $r->get('/reports/popular-books', [ReportController::class, 'popularBooks']);
            $r->get('/reports/top-categories', [ReportController::class, 'topCategories']);
            $r->get('/reports/overdue', [ReportController::class, 'overdue']);
            $r->get('/reports/monthly-issues', [ReportController::class, 'monthlyIssues']);

            $r->get('/settings', [AdminController::class, 'settings']);
        })->add($roles('admin', 'librarian'))->add($auth);

        // Admin only
        $api->group('', static function (RouteCollectorProxy $r): void {
            $r->delete('/books/{id:[0-9]+}', [BookController::class, 'destroy']);
            $r->delete('/categories/{id:[0-9]+}', [CatalogController::class, 'deleteCategory']);
            $r->delete('/authors/{id:[0-9]+}', [CatalogController::class, 'deleteAuthor']);
            $r->delete('/students/{id:[0-9]+}', [StudentController::class, 'destroy']);
            $r->post('/fines/{id:[0-9]+}/waive', [FineController::class, 'waive']);
            $r->get('/audit-logs', [AdminController::class, 'auditLogs']);
            $r->put('/settings', [AdminController::class, 'updateSettings']);
        })->add($roles('admin'))->add($auth);
    });
};
