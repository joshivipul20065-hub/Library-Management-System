<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helpers\ListQuery;
use App\Repositories\CategoryRepositoryInterface;
use App\Validators\Rules;
use App\Validators\Validator;

final class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly AuditService $audit,
    ) {
    }

    /** @return Page */
    public function list(ListQuery $query): array
    {
        return $this->categories->paginate($query);
    }

    /**
     * @param Row $input
     * @return Row
     */
    public function create(array $input): array
    {
        $data = Validator::validate($input, Rules::category());
        $this->assertUniqueName((string) $data['name'], null);
        $id = $this->categories->create((string) $data['name'], $data['description'] === null ? null : (string) $data['description']);
        $this->audit->log('category.create', 'category', $id, ['name' => $data['name']]);

        return $this->get($id);
    }

    /**
     * @param Row $input
     * @return Row
     */
    public function update(int $id, array $input): array
    {
        $this->get($id);
        $data = Validator::validate($input, Rules::category());
        $this->assertUniqueName((string) $data['name'], $id);
        $this->categories->update($id, (string) $data['name'], $data['description'] === null ? null : (string) $data['description']);
        $this->audit->log('category.update', 'category', $id, ['name' => $data['name']]);

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $this->get($id);
        if ($this->categories->countBooks($id) > 0) {
            throw new ConflictException('Move or delete the books in this category first', 'CATEGORY_IN_USE');
        }
        $this->categories->softDelete($id);
        $this->audit->log('category.delete', 'category', $id);
    }

    /** @return Row */
    public function get(int $id): array
    {
        return $this->categories->findById($id) ?? throw new NotFoundException('Category not found');
    }

    /** Finds a category by name, creating it when missing (used by CSV import). */
    public function findOrCreate(string $name): int
    {
        $existing = $this->categories->findByName($name);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $id = $this->categories->create($name, null);
        $this->audit->log('category.create', 'category', $id, ['name' => $name, 'source' => 'import']);

        return $id;
    }

    private function assertUniqueName(string $name, ?int $exceptId): void
    {
        $existing = $this->categories->findByName($name);
        if ($existing !== null && (int) $existing['id'] !== $exceptId) {
            throw new ValidationException('Category already exists', ['name' => 'A category with this name already exists']);
        }
    }
}
