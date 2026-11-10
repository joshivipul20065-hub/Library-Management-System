<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;

interface AuthorRepositoryInterface
{
    public const SORTABLE = ['name' => 'a.name', 'created_at' => 'a.created_at', 'book_count' => 'book_count'];

    /** @return Page */
    public function paginate(ListQuery $query): array;

    /** @return Row|null */
    public function findById(int $id): ?array;

    /** @return Row|null */
    public function findByName(string $name): ?array;

    public function create(string $name): int;

    public function update(int $id, string $name): void;

    public function softDelete(int $id): void;

    public function countBooks(int $id): int;
}
