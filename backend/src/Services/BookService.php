<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helpers\Csv;
use App\Helpers\Database;
use App\Helpers\ListQuery;
use App\Repositories\BookRepositoryInterface;
use App\Repositories\IssueRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;
use App\Validators\Rules;
use App\Validators\Validator;
use Psr\Http\Message\UploadedFileInterface;

final class BookService
{
    public function __construct(
        private readonly Database $db,
        private readonly BookRepositoryInterface $books,
        private readonly IssueRepositoryInterface $issues,
        private readonly ReservationRepositoryInterface $reservations,
        private readonly CategoryService $categories,
        private readonly AuthorService $authors,
        private readonly ReservationQueue $queue,
        private readonly UploadService $uploads,
        private readonly AuditService $audit,
    ) {
    }

    /** @return Page */
    public function list(ListQuery $query): array
    {
        return $this->books->paginate($query);
    }

    /** @return Row */
    public function get(int $id, bool $withCopies = false): array
    {
        $book = $this->books->findById($id) ?? throw new NotFoundException('Book not found');
        unset($book['deleted_at']);
        $book['authors'] = $this->books->authorsOf($id);
        if ($withCopies) {
            $book['copies'] = $this->books->copies($id);
        }

        return $book;
    }

    /**
     * @param Row $input
     * @return Row
     */
    public function create(array $input): array
    {
        $data = Validator::validate($input, Rules::book());
        $id = $this->db->transaction(function () use ($data): int {
            $this->assertValidReferences($data, null);
            $id = $this->books->create($data);
            $this->books->setAuthors($id, $this->authors->resolveNames($data['authors'] ?? []));
            if ($data['cover_url'] !== null) {
                $this->books->setCover($id, (string) $data['cover_url']);
            }
            $copies = (int) ($data['copies'] ?? 0);
            if ($copies > 0) {
                $this->addCopiesLocked($id, $copies, []);
            }
            $this->audit->log('book.create', 'book', $id, ['isbn' => $data['isbn'], 'title' => $data['title'], 'copies' => $copies]);

            return $id;
        });

        return $this->get($id, true);
    }

    /**
     * @param Row $input
     * @return Row
     */
    public function update(int $id, array $input): array
    {
        $previousCover = $this->get($id)['cover_url'];
        $data = Validator::validate($input, Rules::book());
        $this->db->transaction(function () use ($id, $data, $previousCover): void {
            $this->assertValidReferences($data, $id);
            $this->books->update($id, $data);
            $this->books->setAuthors($id, $this->authors->resolveNames($data['authors'] ?? []));
            if ($data['cover_url'] !== null && $data['cover_url'] !== $previousCover) { // never clears an existing cover
                $this->books->setCover($id, (string) $data['cover_url']);
                $this->db->afterCommit(fn () => $this->uploads->delete(is_string($previousCover) ? $previousCover : null));
            }
            $this->audit->log('book.update', 'book', $id, ['isbn' => $data['isbn'], 'title' => $data['title']]);
        });

        return $this->get($id, true);
    }

    public function delete(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $book = $this->books->findById($id, true) ?? throw new NotFoundException('Book not found');
            if ($this->issues->countActiveForBook($id) > 0) {
                throw new ConflictException('This book has copies on loan; return them before deleting', 'BOOK_ON_LOAN');
            }
            $this->reservations->cancelActiveForBook($id);
            $this->books->softDelete($id);
            $this->audit->log('book.delete', 'book', $id, ['isbn' => $book['isbn'], 'title' => $book['title']]);
        });
    }

    /**
     * Adds copies by count (auto-generated barcodes) or by explicit barcodes.
     *
     * @param Row $input
     * @return list<Row> all copies of the book
     */
    public function addCopies(int $bookId, array $input): array
    {
        $data = Validator::validate($input, Rules::copies());
        $barcodes = $data['barcodes'] ?? [];
        $count = (int) ($data['count'] ?? 0);
        if ($barcodes === [] && $count === 0) {
            throw new ValidationException('Provide a number of copies or a list of barcodes', ['count' => 'Enter how many copies to add']);
        }
        $this->db->transaction(function () use ($bookId, $count, $barcodes): void {
            $this->books->findById($bookId, true) ?? throw new NotFoundException('Book not found');
            $added = $this->addCopiesLocked($bookId, $count, $barcodes);
            $this->queue->promote($bookId);
            $this->audit->log('book.add_copies', 'book', $bookId, ['barcodes' => $added]);
        });

        return $this->books->copies($bookId);
    }

    /** @return Row */
    public function uploadCover(int $bookId, ?UploadedFileInterface $file): array
    {
        $previous = $this->get($bookId)['cover_url'];
        $url = $this->uploads->storeImage($file);
        $this->books->setCover($bookId, $url);
        $this->uploads->delete(is_string($previous) ? $previous : null);
        $this->audit->log('book.cover', 'book', $bookId, ['cover_url' => $url]);

        return $this->get($bookId, true);
    }

    /** Removes the cover (and deletes the file if it was uploaded here). */
    public function removeCover(int $bookId): void
    {
        $previous = $this->get($bookId)['cover_url'];
        $this->books->setCover($bookId, null);
        $this->uploads->delete(is_string($previous) ? $previous : null);
        $this->audit->log('book.cover_removed', 'book', $bookId, ['cover_url' => $previous]);
    }

    /**
     * Columns: isbn, title, authors (separated by ";"), category, publisher, edition, published_year,
     * language, description, shelf_location, copies. Each row is imported in its own transaction.
     *
     * @return array{created: int, failed: int, errors: list<array{row: int, message: string, details: array<string, mixed>}>}
     */
    public function import(?UploadedFileInterface $file): array
    {
        $rows = Csv::parseUpload($file, ['isbn', 'title']);
        $created = 0;
        $errors = [];
        foreach ($rows as $index => $row) {
            try {
                $this->db->transaction(function () use ($row): void {
                    if (($row['category'] ?? '') !== '') {
                        $row['category_id'] = (string) $this->categories->findOrCreate(mb_substr($row['category'], 0, 100));
                    }
                    $data = Validator::validate($row, Rules::book());
                    $this->assertValidReferences($data, null);
                    $id = $this->books->create($data);
                    $this->books->setAuthors($id, $this->authors->resolveNames($data['authors'] ?? []));
                    $copies = (int) ($data['copies'] ?? 1);
                    if ($copies > 0) {
                        $this->addCopiesLocked($id, $copies, []);
                    }
                });
                $created++;
            } catch (ApiException $e) {
                $errors[] = ['row' => $index + 2, 'message' => $e->getMessage(), 'details' => $e->details];
            }
        }
        $this->audit->log('book.import', 'book', null, ['created' => $created, 'failed' => count($errors)]);

        return ['created' => $created, 'failed' => count($errors), 'errors' => $errors];
    }

    /**
     * @param list<string> $barcodes
     * @return list<string> barcodes added
     */
    private function addCopiesLocked(int $bookId, int $count, array $barcodes): array
    {
        if ($barcodes === []) {
            $sequence = $this->books->countCopies($bookId);
            for ($i = 0; $i < $count; $i++) {
                do {
                    $candidate = sprintf('LIB-%05d-%03d', $bookId, ++$sequence);
                } while ($this->books->barcodeExists($candidate));
                $barcodes[] = $candidate;
            }
        }
        foreach ($barcodes as $barcode) {
            if ($this->books->barcodeExists($barcode)) {
                throw new ValidationException('Barcode already in use', ['barcodes' => "Barcode {$barcode} is already assigned to another copy"]);
            }
            $this->books->addCopy($bookId, $barcode);
        }
        $this->books->adjustCopyCounts($bookId, count($barcodes), count($barcodes));

        return $barcodes;
    }

    /** @param Row $data */
    private function assertValidReferences(array $data, ?int $bookId): void
    {
        if ($this->books->isbnExists((string) $data['isbn'], $bookId)) {
            throw new ValidationException('ISBN already exists', ['isbn' => 'A book with this ISBN already exists']);
        }
        if ($data['category_id'] !== null) {
            try {
                $this->categories->get((int) $data['category_id']);
            } catch (NotFoundException) {
                throw new ValidationException('Unknown category', ['category_id' => 'Select an existing category']);
            }
        }
    }
}
