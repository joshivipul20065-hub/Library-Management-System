<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Http;
use App\Helpers\ListQuery;
use App\Repositories\IssueRepositoryInterface;
use App\Repositories\StudentRepositoryInterface;
use App\Services\StudentService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class StudentController
{
    public function __construct(private readonly StudentService $students)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, $this->students->list(ListQuery::fromRequest($request, StudentRepositoryInterface::SORTABLE, 'name', 'asc')));
    }

    public function show(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->students->get(Http::id($id))]);
    }

    public function byRollNo(ResponseInterface $response, string $rollNo): ResponseInterface
    {
        return Http::json($response, ['data' => $this->students->lookupByRollNo(rawurldecode($rollNo))]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->students->create(Http::body($request))], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->students->update(Http::id($id), Http::body($request))]);
    }

    public function destroy(ResponseInterface $response, string $id): ResponseInterface
    {
        $this->students->delete(Http::id($id));

        return Http::noContent($response);
    }

    public function suspend(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->students->setStatus(Http::id($id), 'suspended')]);
    }

    public function activate(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->students->setStatus(Http::id($id), 'active')]);
    }

    public function import(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = $request->getUploadedFiles()['file'] ?? null;

        return Http::json($response, ['data' => $this->students->import($file instanceof UploadedFileInterface ? $file : null)]);
    }

    public function history(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json(
            $response,
            $this->students->history(Http::id($id), ListQuery::fromRequest($request, IssueRepositoryInterface::SORTABLE, 'issue_date'))
        );
    }
}
