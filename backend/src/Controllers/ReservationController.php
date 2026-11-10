<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Http;
use App\Helpers\ListQuery;
use App\Repositories\ReservationRepositoryInterface;
use App\Services\ReservationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ReservationController
{
    public function __construct(private readonly ReservationService $reservations)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = ListQuery::fromRequest($request, ReservationRepositoryInterface::SORTABLE, 'created_at', 'asc');

        return Http::json($response, $this->reservations->list($query));
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->reservations->reserve(Http::body($request))], 201);
    }

    public function destroy(ResponseInterface $response, string $id): ResponseInterface
    {
        $this->reservations->cancel(Http::id($id));

        return Http::noContent($response);
    }

    public function fulfil(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->reservations->fulfil(Http::id($id))], 201);
    }
}
