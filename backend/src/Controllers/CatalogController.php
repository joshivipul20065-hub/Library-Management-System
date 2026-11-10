<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Http;
use App\Helpers\ListQuery;
use App\Repositories\AuthorRepositoryInterface;
use App\Repositories\CategoryRepositoryInterface;
use App\Services\AuthorService;
use App\Services\CategoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Category and author CRUD. */
final class CatalogController
{
    public function __construct(private readonly CategoryService $categories, private readonly AuthorService $authors)
    {
    }

    public function categories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, $this->categories->list(ListQuery::fromRequest($request, CategoryRepositoryInterface::SORTABLE, 'name', 'asc')));
    }

    public function createCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->categories->create(Http::body($request))], 201);
    }

    public function updateCategory(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->categories->update(Http::id($id), Http::body($request))]);
    }

    public function deleteCategory(ResponseInterface $response, string $id): ResponseInterface
    {
        $this->categories->delete(Http::id($id));

        return Http::noContent($response);
    }

    public function authors(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, $this->authors->list(ListQuery::fromRequest($request, AuthorRepositoryInterface::SORTABLE, 'name', 'asc')));
    }

    public function createAuthor(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->authors->create(Http::body($request))], 201);
    }

    public function updateAuthor(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->authors->update(Http::id($id), Http::body($request))]);
    }

    public function deleteAuthor(ResponseInterface $response, string $id): ResponseInterface
    {
        $this->authors->delete(Http::id($id));

        return Http::noContent($response);
    }
}
