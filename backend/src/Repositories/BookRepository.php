<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\BookCopy;

final class BookRepository extends BaseRepository implements BookRepositoryInterface
{
    private const AUTHORS_SUBQUERY = "(SELECT GROUP_CONCAT(a.name ORDER BY a.name SEPARATOR ', ')
        FROM book_authors ba JOIN authors a ON a.id = ba.author_id WHERE ba.book_id = b.id) AS authors";
    private const COPY_SELECT = 'SELECT bc.*, b.title AS book_title FROM book_copies bc JOIN books b ON b.id = bc.book_id';

    public function paginate(ListQuery $query): array
    {
        $where = ['b.deleted_at IS NULL'];
        $params = [];
        if (($q = $query->filter('q')) !== null) {
            $where[] = '(b.title LIKE :q1 OR b.isbn LIKE :q2 OR b.publisher LIKE :q3 OR EXISTS (
                SELECT 1 FROM book_authors sba JOIN authors sa ON sa.id = sba.author_id
                WHERE sba.book_id = b.id AND sa.name LIKE :q4))';
            $params += ['q1' => self::like($q), 'q2' => self::like($q), 'q3' => self::like($q), 'q4' => self::like($q)];
        }
        if (($title = $query->filter('title')) !== null) {
            $where[] = 'b.title LIKE :title';
            $params['title'] = self::like($title);
        }
        if (($isbn = $query->filter('isbn')) !== null) {
            $where[] = 'b.isbn = :isbn';
            $params['isbn'] = $isbn;
        }
        if (($author = $query->filter('author')) !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM book_authors fba JOIN authors fa ON fa.id = fba.author_id
                WHERE fba.book_id = b.id AND fa.name LIKE :author)';
            $params['author'] = self::like($author);
        }
        if (($categoryId = $query->filter('category_id')) !== null) {
            $where[] = 'b.category_id = :category_id';
            $params['category_id'] = (int) $categoryId;
        }
        if (($language = $query->filter('language')) !== null) {
            $where[] = 'b.language = :language';
            $params['language'] = $language;
        }
        $available = $query->filter('available');
        if ($available === '1' || $available === 'true') {
            $where[] = 'b.available_copies > 0';
        } elseif ($available === '0' || $available === 'false') {
            $where[] = 'b.available_copies = 0';
        }

        $select = 'b.id, b.isbn, b.title, b.publisher, b.edition, b.published_year, b.language, b.cover_url,
            b.shelf_location, b.total_copies, b.available_copies, b.category_id, c.name AS category_name, b.created_at, '
            . self::AUTHORS_SUBQUERY;

        return $this->paginateQuery(
            $select,
            'FROM books b LEFT JOIN categories c ON c.id = b.category_id WHERE ' . implode(' AND ', $where),
            $params,
            $query
        );
    }

    public function findById(int $id, bool $forUpdate = false): ?array
    {
        return $this->db->one(
            'SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON c.id = b.category_id
             WHERE b.id = :id AND b.deleted_at IS NULL' . ($forUpdate ? ' FOR UPDATE OF b' : ''),
            ['id' => $id]
        );
    }

    public function authorsOf(int $bookId): array
    {
        return $this->db->all(
            'SELECT a.id, a.name FROM book_authors ba JOIN authors a ON a.id = ba.author_id
             WHERE ba.book_id = :id ORDER BY a.name',
            ['id' => $bookId]
        );
    }

    public function isbnExists(string $isbn, ?int $exceptBookId = null): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM books WHERE isbn = :isbn AND id <> :id',
            ['isbn' => $isbn, 'id' => $exceptBookId ?? 0]
        ) !== null;
    }

    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO books (isbn, title, publisher, edition, published_year, category_id, language, description, shelf_location)
             VALUES (:isbn, :title, :publisher, :edition, :published_year, :category_id, :language, :description, :shelf_location)',
            $this->bookParams($data)
        );
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE books SET isbn = :isbn, title = :title, publisher = :publisher, edition = :edition,
                published_year = :published_year, category_id = :category_id, language = :language,
                description = :description, shelf_location = :shelf_location WHERE id = :id',
            $this->bookParams($data) + ['id' => $id]
        );
    }

    public function setAuthors(int $bookId, array $authorIds): void
    {
        $this->db->execute('DELETE FROM book_authors WHERE book_id = :id', ['id' => $bookId]);
        foreach (array_unique($authorIds) as $authorId) {
            $this->db->execute(
                'INSERT INTO book_authors (book_id, author_id) VALUES (:book, :author)',
                ['book' => $bookId, 'author' => $authorId]
            );
        }
    }

    public function setCover(int $bookId, ?string $url): void
    {
        $this->db->execute('UPDATE books SET cover_url = :url WHERE id = :id', ['url' => $url, 'id' => $bookId]);
    }

    public function softDelete(int $id): void
    {
        // Free the unique ISBN so the title can be catalogued again.
        $this->db->execute(
            "UPDATE books SET deleted_at = NOW(), isbn = LEFT(CONCAT('del-', id, '-', isbn), 20) WHERE id = :id",
            ['id' => $id]
        );
    }

    public function adjustCopyCounts(int $bookId, int $totalDelta, int $availableDelta): void
    {
        $this->db->execute(
            'UPDATE books SET total_copies = total_copies + :total, available_copies = available_copies + :available
             WHERE id = :id',
            ['total' => $totalDelta, 'available' => $availableDelta, 'id' => $bookId]
        );
    }

    public function copies(int $bookId): array
    {
        return $this->db->all(
            "SELECT bc.id, bc.barcode, bc.status, bc.created_at, i.id AS issue_id, i.due_date,
                u.name AS borrower_name, s.roll_no AS borrower_roll_no
             FROM book_copies bc
             LEFT JOIN issues i ON i.copy_id = bc.id AND i.status IN ('issued','overdue')
             LEFT JOIN students s ON s.id = i.student_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE bc.book_id = :id ORDER BY bc.id",
            ['id' => $bookId]
        );
    }

    public function addCopy(int $bookId, string $barcode): int
    {
        return $this->db->insert(
            'INSERT INTO book_copies (book_id, barcode) VALUES (:book, :barcode)',
            ['book' => $bookId, 'barcode' => $barcode]
        );
    }

    public function barcodeExists(string $barcode): bool
    {
        return $this->db->scalar('SELECT 1 FROM book_copies WHERE barcode = :barcode', ['barcode' => $barcode]) !== null;
    }

    public function countCopies(int $bookId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM book_copies WHERE book_id = :id', ['id' => $bookId]);
    }

    public function findCopyByBarcode(string $barcode, bool $forUpdate = false): ?BookCopy
    {
        $row = $this->db->one(
            self::COPY_SELECT . ' WHERE bc.barcode = :barcode AND b.deleted_at IS NULL' . ($forUpdate ? ' FOR UPDATE OF bc' : ''),
            ['barcode' => $barcode]
        );

        return $row === null ? null : BookCopy::fromRow($row);
    }

    public function findCopy(int $copyId, bool $forUpdate = false): ?BookCopy
    {
        $row = $this->db->one(
            self::COPY_SELECT . ' WHERE bc.id = :id' . ($forUpdate ? ' FOR UPDATE OF bc' : ''),
            ['id' => $copyId]
        );

        return $row === null ? null : BookCopy::fromRow($row);
    }

    public function firstAvailableCopy(int $bookId): ?BookCopy
    {
        $row = $this->db->one(
            self::COPY_SELECT . " WHERE bc.book_id = :id AND bc.status = 'available' ORDER BY bc.id LIMIT 1",
            ['id' => $bookId]
        );

        return $row === null ? null : BookCopy::fromRow($row);
    }

    public function setCopyStatus(int $copyId, string $status): void
    {
        $this->db->execute('UPDATE book_copies SET status = :status WHERE id = :id', ['status' => $status, 'id' => $copyId]);
    }

    /**
     * @param Row $data
     * @return Row
     */
    private function bookParams(array $data): array
    {
        return [
            'isbn' => $data['isbn'],
            'title' => $data['title'],
            'publisher' => $data['publisher'] ?? null,
            'edition' => $data['edition'] ?? null,
            'published_year' => $data['published_year'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'language' => $data['language'] ?? 'English',
            'description' => $data['description'] ?? null,
            'shelf_location' => $data['shelf_location'] ?? null,
        ];
    }
}
