<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;

final class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function paginate(ListQuery $query): array
    {
        $where = 'c.deleted_at IS NULL';
        $params = [];
        if (($q = $query->filter('q')) !== null) {
            $where .= ' AND c.name LIKE :q';
            $params['q'] = self::like($q);
        }

        return $this->paginateQuery(
            'c.id, c.name, c.description, c.created_at,
             (SELECT COUNT(*) FROM books b WHERE b.category_id = c.id AND b.deleted_at IS NULL) AS book_count',
            "FROM categories c WHERE {$where}",
            $params,
            $query
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->one('SELECT * FROM categories WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
    }

    public function findByName(string $name): ?array
    {
        return $this->db->one('SELECT * FROM categories WHERE name = :name AND deleted_at IS NULL', ['name' => $name]);
    }

    public function create(string $name, ?string $description): int
    {
        return $this->db->insert(
            'INSERT INTO categories (name, description) VALUES (:name, :description)',
            ['name' => $name, 'description' => $description]
        );
    }

    public function update(int $id, string $name, ?string $description): void
    {
        $this->db->execute(
            'UPDATE categories SET name = :name, description = :description WHERE id = :id',
            ['name' => $name, 'description' => $description, 'id' => $id]
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE categories SET deleted_at = NOW(), name = LEFT(CONCAT('deleted-', id, '-', name), 100) WHERE id = :id",
            ['id' => $id]
        );
    }

    public function countBooks(int $id): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM books WHERE category_id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }
}
