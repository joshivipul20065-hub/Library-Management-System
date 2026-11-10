<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csv;
use App\Helpers\Http;
use App\Services\ReportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Every report returns JSON, or CSV with `?format=csv`. */
final class ReportController
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function summary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $summary = $this->reports->summary();
        if (Http::wantsCsv($request)) {
            return Csv::respond($response, 'summary.csv', ['metric', 'value'], array_map(
                static fn (string $key, int|float $value): array => [$key, $value],
                array_keys($summary),
                $summary
            ));
        }

        return Http::json($response, ['data' => $summary]);
    }

    public function popularBooks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->reports->popularBooks($request->getQueryParams());

        return $this->respond($request, $response, 'popular-books.csv', $rows, [
            'id' => 'Book ID', 'title' => 'Title', 'authors' => 'Authors', 'category_name' => 'Category', 'isbn' => 'ISBN', 'issue_count' => 'Times issued',
        ]);
    }

    public function topCategories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->reports->topCategories($request->getQueryParams());

        return $this->respond($request, $response, 'top-categories.csv', $rows, ['id' => 'Category ID', 'name' => 'Category', 'issue_count' => 'Times issued']);
    }

    public function overdue(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->respond($request, $response, 'overdue.csv', $this->reports->overdue(), [
            'id' => 'Issue ID', 'book_title' => 'Title', 'barcode' => 'Barcode', 'student_name' => 'Student', 'roll_no' => 'Roll no',
            'student_email' => 'Email', 'phone' => 'Phone', 'issue_date' => 'Issued', 'due_date' => 'Due', 'days_overdue' => 'Days overdue',
            'estimated_fine' => 'Estimated fine',
        ]);
    }

    public function monthlyIssues(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->respond($request, $response, 'monthly-issues.csv', $this->reports->monthlyIssues($request->getQueryParams()), [
            'month' => 'Month', 'issues' => 'Issues', 'returns' => 'Returns',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $columns key => CSV header
     */
    private function respond(ServerRequestInterface $request, ResponseInterface $response, string $filename, array $rows, array $columns): ResponseInterface
    {
        if (!Http::wantsCsv($request)) {
            return Http::json($response, ['data' => $rows]);
        }
        $lines = array_map(
            static fn (array $row): array => array_map(static fn (string $key): mixed => $row[$key] ?? '', array_keys($columns)),
            $rows
        );

        return Csv::respond($response, $filename, array_values($columns), $lines);
    }
}
