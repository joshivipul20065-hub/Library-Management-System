<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\NotFoundException;
use App\Helpers\Http;
use App\Helpers\ListQuery;
use App\Repositories\BookRepositoryInterface;
use App\Services\BookService;
use App\Services\IsbnLookupService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class BookController
{
    public function __construct(
        private readonly BookService $books,
        private readonly BookRepositoryInterface $repository,
        private readonly IsbnLookupService $isbnLookup,
    ) {
    }

    public function lookupIsbn(ResponseInterface $response, string $isbn): ResponseInterface
    {
        return Http::json($response, ['data' => $this->isbnLookup->lookup(rawurldecode($isbn))]);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, $this->books->list(ListQuery::fromRequest($request, BookRepositoryInterface::SORTABLE, 'title', 'asc')));
    }

    public function show(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->books->get(Http::id($id))]);
    }

    public function copies(ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->books->get(Http::id($id), true)['copies']]);
    }

    public function copyByBarcode(ResponseInterface $response, string $barcode): ResponseInterface
    {
        $copy = $this->repository->findCopyByBarcode($barcode) ?? throw new NotFoundException('No copy with this barcode', 'COPY_NOT_FOUND');

        return Http::json($response, ['data' => $copy->toArray()]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->books->create(Http::body($request))], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->books->update(Http::id($id), Http::body($request))]);
    }

    public function destroy(ResponseInterface $response, string $id): ResponseInterface
    {
        $this->books->delete(Http::id($id));

        return Http::noContent($response);
    }

    public function addCopies(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        return Http::json($response, ['data' => $this->books->addCopies(Http::id($id), Http::body($request))], 201);
    }

    public function removeCover(ResponseInterface $response, string $id): ResponseInterface
    {
        $this->books->removeCover(Http::id($id));

        return Http::noContent($response);
    }

    public function uploadCover(ServerRequestInterface $request, ResponseInterface $response, string $id): ResponseInterface
    {
        $file = $request->getUploadedFiles()['cover'] ?? null;

        return Http::json($response, ['data' => $this->books->uploadCover(Http::id($id), $file instanceof UploadedFileInterface ? $file : null)]);
    }

    public function import(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = $request->getUploadedFiles()['file'] ?? null;

        return Http::json($response, ['data' => $this->books->import($file instanceof UploadedFileInterface ? $file : null)]);
    }
}
