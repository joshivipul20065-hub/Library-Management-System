<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AuthorizationException;
use App\Helpers\Http;
use App\Helpers\ListQuery;
use App\Helpers\RequestContext;
use App\Repositories\FineRepositoryInterface;
use App\Repositories\IssueRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;
use App\Services\AuthService;
use App\Services\CirculationService;
use App\Services\FineService;
use App\Services\NotificationService;
use App\Services\ReportService;
use App\Services\ReservationService;
use App\Services\StudentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Self-service endpoints scoped to the signed-in user. */
final class MeController
{
    public function __construct(
        private readonly RequestContext $context,
        private readonly CirculationService $circulation,
        private readonly ReservationService $reservations,
        private readonly FineService $fines,
        private readonly NotificationService $notifications,
        private readonly ReportService $reports,
        private readonly StudentService $students,
        private readonly AuthService $auth,
    ) {
    }

    public function dashboard(ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->reports->studentDashboard($this->studentId())]);
    }

    public function issues(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = ListQuery::fromRequest($request, IssueRepositoryInterface::SORTABLE, 'issue_date');

        return Http::json($response, $this->circulation->list($query->withFilter('student_id', (string) $this->studentId())));
    }

    public function reservations(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = ListQuery::fromRequest($request, ReservationRepositoryInterface::SORTABLE, 'created_at');

        return Http::json($response, $this->reservations->list($query->withFilter('student_id', (string) $this->studentId())));
    }

    public function fines(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = ListQuery::fromRequest($request, FineRepositoryInterface::SORTABLE, 'created_at');

        return Http::json($response, $this->fines->list($query->withFilter('student_id', (string) $this->studentId())));
    }

    public function notifications(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = ListQuery::fromRequest($request, ['created_at' => 'id'], 'created_at');

        return Http::json($response, $this->notifications->list($this->context->user()->id, $query));
    }

    public function readNotification(ResponseInterface $response, string $id): ResponseInterface
    {
        $this->notifications->markRead(Http::id($id), $this->context->user()->id);

        return Http::noContent($response);
    }

    public function readAllNotifications(ResponseInterface $response): ResponseInterface
    {
        $this->notifications->markAllRead($this->context->user()->id);

        return Http::noContent($response);
    }

    public function updateProfile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->context->user();
        $this->students->updateOwnProfile($user, Http::body($request));

        return Http::json($response, ['data' => $this->auth->me($user->id)]);
    }

    private function studentId(): int
    {
        return $this->context->user()->studentId ?? throw new AuthorizationException('Only students have a borrowing record');
    }
}
