<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;

final class AuthorRepository extends BaseRepository implements AuthorRepositoryInterface
{
    public function paginate(ListQuery $query): array
    {
        $where = 'a.deleted_at IS NULL';
        $params = [];
        if (($q = $query->filter('q')) !== null) {
            $where .= ' AND a.name LIKE :q';
            $params['q'] = self::like($q);
        }

        return $this->paginateQuery(
            'a.id, a.name, a.created_at,
             (SELECT COUNT(*) FROM book_authors ba JOIN books b ON b.id = ba.book_id
                WHERE ba.author_id = a.id AND b.deleted_at IS NULL) AS book_count',
            "FROM authors a WHERE {$where}",
            $params,
            $query
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->one('SELECT * FROM authors WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
    }

    public function findByName(string $name): ?array
    {
        return $this->db->one(
            'SELECT * FROM authors WHERE name = :name AND deleted_at IS NULL ORDER BY id LIMIT 1',
            ['name' => $name]
        );
    }

    public function create(string $name): int
    {
        return $this->db->insert('INSERT INTO authors (name) VALUES (:name)', ['name' => $name]);
    }

    public function update(int $id, string $name): void
    {
        $this->db->execute('UPDATE authors SET name = :name WHERE id = :id', ['name' => $name, 'id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->db->execute('UPDATE authors SET deleted_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public function countBooks(int $id): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM book_authors ba JOIN books b ON b.id = ba.book_id
             WHERE ba.author_id = :id AND b.deleted_at IS NULL',
            ['id' => $id]
        );
    }
}
