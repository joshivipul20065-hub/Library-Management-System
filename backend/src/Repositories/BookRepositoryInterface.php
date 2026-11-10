<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\BookCopy;

interface BookRepositoryInterface
{
    public const SORTABLE = [
        'title' => 'b.title',
        'created_at' => 'b.created_at',
        'published_year' => 'b.published_year',
        'available' => 'b.available_copies',
        'isbn' => 'b.isbn',
    ];

    /** @return Page */
    public function paginate(ListQuery $query): array;

    /** @return Row|null */
    public function findById(int $id, bool $forUpdate = false): ?array;

    /** @return list<Row> */
    public function authorsOf(int $bookId): array;

    public function isbnExists(string $isbn, ?int $exceptBookId = null): bool;

    /** @param Row $data */
    public function create(array $data): int;

    /** @param Row $data */
    public function update(int $id, array $data): void;

    /** @param list<int> $authorIds */
    public function setAuthors(int $bookId, array $authorIds): void;

    public function setCover(int $bookId, ?string $url): void;

    public function softDelete(int $id): void;

    public function adjustCopyCounts(int $bookId, int $totalDelta, int $availableDelta): void;

    /** @return list<Row> */
    public function copies(int $bookId): array;

    public function addCopy(int $bookId, string $barcode): int;

    public function barcodeExists(string $barcode): bool;

    public function countCopies(int $bookId): int;

    public function findCopyByBarcode(string $barcode, bool $forUpdate = false): ?BookCopy;

    public function findCopy(int $copyId, bool $forUpdate = false): ?BookCopy;

    public function firstAvailableCopy(int $bookId): ?BookCopy;

    public function setCopyStatus(int $copyId, string $status): void;
}
