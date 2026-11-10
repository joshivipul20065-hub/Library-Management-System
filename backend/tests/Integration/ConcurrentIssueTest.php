<?php

declare(strict_types=1);

namespace Tests\Integration;

/**
 * Starts several PHP processes that try to issue the same copy at the same instant.
 * Exactly one must succeed; the rest must be rejected, and the book counters must stay consistent.
 */
final class ConcurrentIssueTest extends IntegrationTestCase
{
    private const WORKERS = 8;

    public function testOnlyOneOfManySimultaneousRequestsCanIssueTheSameCopy(): void
    {
        [$bookId, [$barcode]] = $this->createBook('RACE', 1);
        $students = [];
        for ($i = 1; $i <= self::WORKERS; $i++) {
            $students[] = $this->createStudent("RACE-{$i}");
        }

        $startAt = sprintf('%.3f', microtime(true) + 2.0);
        $processes = [];
        foreach ($students as $studentId) {
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, __DIR__ . '/issue_worker.php', (string) $studentId, $barcode, $startAt],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            self::assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $results[] = trim((string) stream_get_contents($pipes[1])) . trim((string) stream_get_contents($pipes[2]));
            proc_close($process);
        }

        $outcomes = array_count_values($results);
        self::assertSame(1, $outcomes['OK'] ?? 0, 'Exactly one worker issues the copy: ' . json_encode($results));
        self::assertSame(self::WORKERS - 1, $outcomes['COPY_UNAVAILABLE'] ?? 0, 'All others are rejected: ' . json_encode($results));
        self::assertSame(1, (int) self::$db->scalar("SELECT COUNT(*) FROM issues WHERE status IN ('issued','overdue')"));
        self::assertSame(0, $this->availableCopies($bookId));
    }
}
