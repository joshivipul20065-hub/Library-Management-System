<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Http;
use App\Helpers\ListQuery;
use App\Repositories\FineRepositoryInterface;
use App\Services\FineService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class FineController
{
    public function __construct(private readonly FineService $fines)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, $this->fines->list(ListQuery::fromRequest($request, FineRepositoryInterface::SORTABLE, 'created_at')));
    }

    public function pay(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->fines->pay(Http::id($id))]);
    }

    public function waive(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->fines->waive(Http::id($id), Http::body($request))]);
    }
}
