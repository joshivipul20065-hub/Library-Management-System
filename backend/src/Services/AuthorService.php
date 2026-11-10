<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Helpers\ListQuery;
use App\Repositories\AuthorRepositoryInterface;
use App\Validators\Rules;
use App\Validators\Validator;

final class AuthorService
{
    public function __construct(
        private readonly AuthorRepositoryInterface $authors,
        private readonly AuditService $audit,
    ) {
    }

    /** @return Page */
    public function list(ListQuery $query): array
    {
        return $this->authors->paginate($query);
    }

    /**
     * @param Row $input
     * @return Row
     */
    public function create(array $input): array
    {
        $name = (string) Validator::validate($input, Rules::author())['name'];
        $id = $this->authors->create($name);
        $this->audit->log('author.create', 'author', $id, ['name' => $name]);

        return $this->get($id);
    }

    /**
     * @param Row $input
     * @return Row
     */
    public function update(int $id, array $input): array
    {
        $this->get($id);
        $name = (string) Validator::validate($input, Rules::author())['name'];
        $this->authors->update($id, $name);
        $this->audit->log('author.update', 'author', $id, ['name' => $name]);

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $this->get($id);
        if ($this->authors->countBooks($id) > 0) {
            throw new ConflictException('This author is linked to books; update those books first', 'AUTHOR_IN_USE');
        }
        $this->authors->softDelete($id);
        $this->audit->log('author.delete', 'author', $id);
    }

    /** @return Row */
    public function get(int $id): array
    {
        return $this->authors->findById($id) ?? throw new NotFoundException('Author not found');
    }

    /**
     * Resolves author names to ids, creating missing authors.
     *
     * @param list<string> $names
     * @return list<int>
     */
    public function resolveNames(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $existing = $this->authors->findByName($name);
            $ids[] = $existing !== null ? (int) $existing['id'] : $this->authors->create($name);
        }

        return $ids;
    }
}
