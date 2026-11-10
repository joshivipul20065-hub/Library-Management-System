<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Http;
use App\Helpers\ListQuery;
use App\Repositories\IssueRepositoryInterface;
use App\Services\CirculationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class IssueController
{
    public function __construct(private readonly CirculationService $circulation)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, $this->circulation->list(ListQuery::fromRequest($request, IssueRepositoryInterface::SORTABLE, 'id')));
    }

    public function show(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->circulation->details(Http::id($id))]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->circulation->issue(Http::body($request))], 201);
    }

    public function returnBook(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->circulation->returnIssue(Http::id($id))]);
    }

    public function renew(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->circulation->renew(Http::id($id))]);
    }

    public function markLost(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->circulation->markLost(Http::id($id))]);
    }

    public function finePreview(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->circulation->finePreview(Http::id($id))]);
    }

    public function remind(ResponseInterface $response, string $id): ResponseInterface
    {
        $this->circulation->remind(Http::id($id));

        return Http::json($response, ['message' => 'Reminder sent']);
    }
}
