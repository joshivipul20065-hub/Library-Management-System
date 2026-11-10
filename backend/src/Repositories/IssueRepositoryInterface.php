<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ListQuery;
use App\Models\Issue;

interface IssueRepositoryInterface
{
    public const SORTABLE = [
        'id' => 'i.id',
        'issue_date' => 'i.issue_date',
        'due_date' => 'i.due_date',
        'return_date' => 'i.return_date',
        'status' => 'i.status',
        'student' => 'u.name',
        'title' => 'b.title',
    ];

    /**
     * Filters: status (issued|returned|overdue|lost|active), overdue=1, student_id, book_id,
     * barcode, from, to (issue date), due_before, q (title / barcode / roll no / student name).
     *
     * @return Page
     */
    public function paginate(ListQuery $query): array;

    public function findById(int $id, bool $forUpdate = false): ?Issue;

    /** @return Row|null */
    public function details(int $id): ?array;

    public function create(int $copyId, int $studentId, ?int $issuedBy, string $issueDate, string $dueDate): int;

    public function markReturned(int $id, string $returnDate): void;

    public function markLost(int $id, string $date): void;

    public function renew(int $id, string $dueDate): void;

    public function countActiveForStudent(int $studentId): int;

    public function studentHasActiveIssueOfBook(int $studentId, int $bookId): bool;

    public function countActiveForBook(int $bookId): int;

    /**
     * Flags issued loans past their due date as overdue and returns the newly flagged rows.
     *
     * @return list<Row>
     */
    public function flagOverdue(string $today): array;
}
