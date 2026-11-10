<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\NotFoundException;
use App\Validators\Field;
use App\Validators\Validator;
use Psr\Log\LoggerInterface;

/** Looks up book details by ISBN on Open Library (free, no API key). Results only pre-fill the form; staff review them. */
final class IsbnLookupService
{
    private const ENDPOINT = 'https://openlibrary.org/api/books?format=json&jscmd=data&bibkeys=ISBN:';
    private const SEARCH_ENDPOINT = 'https://openlibrary.org/search.json?';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /** @return Row */
    public function lookup(string $isbn): array
    {
        $isbn = (string) Validator::validate(['isbn' => $isbn], ['isbn' => Field::isbn()])['isbn'];

        $book = self::map($this->getJson(self::ENDPOINT . $isbn), $isbn)
            ?? throw new NotFoundException('No details found for this ISBN on Open Library', 'ISBN_NOT_FOUND');

        // Many edition records have no cover image; fall back to another edition of the same work.
        if ($book['cover_url'] === null && $book['authors'] !== []) {
            try {
                $book['cover_url'] = $this->findCoverUrl((string) $book['title'], (string) $book['authors'][0]);
            } catch (ApiException) {
                // The details are still useful without a cover.
            }
        }

        return $book;
    }

    /**
     * Finds a cover image by title + author (Open Library search). Matching by title rather than ISBN avoids
     * picking up the cover of an unrelated edition when an ISBN is wrong or made up.
     */
    public function findCoverUrl(string $title, string $author): ?string
    {
        // Exact title + full author first; then drop any subtitle and search by surname (handles "Sapiens: A Brief…",
        // "Stuart J. Russell" vs "Stuart Russell", accents).
        $nameParts = preg_split('/\s+/', trim($author)) ?: [$author];
        $surname = (string) end($nameParts);
        $attempts = [[$title, $author], [trim((string) preg_split('/[:,]/', $title)[0]), $surname]];
        foreach (array_unique($attempts, SORT_REGULAR) as [$searchTitle, $searchAuthor]) {
            $query = http_build_query(['title' => $searchTitle, 'author' => $searchAuthor, 'limit' => 5, 'fields' => 'cover_i']);
            $cover = self::coverFromSearch($this->getJson(self::SEARCH_ENDPOINT . $query));
            if ($cover !== null) {
                return $cover;
            }
        }

        return null;
    }

    /**
     * First result that has a cover, from an Open Library search.json response.
     *
     * @param array<mixed> $payload
     */
    public static function coverFromSearch(array $payload): ?string
    {
        foreach (is_array($payload['docs'] ?? null) ? $payload['docs'] : [] as $doc) {
            $coverId = is_array($doc) ? ($doc['cover_i'] ?? null) : null;
            if (is_int($coverId) && $coverId > 0) {
                return "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg";
            }
        }

        return null;
    }

    /**
     * Normalises an Open Library `jscmd=data` response into the book form's fields.
     *
     * @param array<mixed> $payload
     * @return Row|null
     */
    public static function map(array $payload, string $isbn): ?array
    {
        $record = $payload["ISBN:{$isbn}"] ?? null;
        if (!is_array($record) || !is_string($record['title'] ?? null) || trim($record['title']) === '') {
            return null;
        }
        $title = trim($record['title']);
        $authors = self::names($record['authors'] ?? null);
        $publishers = self::names($record['publishers'] ?? null);

        // ponytail: Open Library is crowd-edited and some popular ISBNs carry spam records whose "publisher" repeats the
        // title and that have no authors. Treat those as not found; extend this check if other spam patterns appear.
        if ($authors === [] && ($publishers[0] ?? null) === $title) {
            return null;
        }

        $year = preg_match('/\b(1\d{3}|20\d{2})\b/', is_string($record['publish_date'] ?? null) ? $record['publish_date'] : '', $m) === 1
            ? (int) $m[1]
            : null;
        $cover = $record['cover']['large'] ?? $record['cover']['medium'] ?? null;

        return [
            'isbn' => $isbn,
            'title' => mb_substr($title, 0, 255),
            'authors' => array_slice(array_map(static fn (string $a): string => mb_substr($a, 0, 150), $authors), 0, 20),
            'publisher' => isset($publishers[0]) ? mb_substr($publishers[0], 0, 150) : null,
            'published_year' => $year,
            'subjects' => array_slice(self::names($record['subjects'] ?? null), 0, 10),
            'cover_url' => is_string($cover) ? $cover : null,
            'source_url' => is_string($record['url'] ?? null) ? $record['url'] : null,
        ];
    }

    /** @return array<mixed> */
    private function getJson(string $url): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'LibraryManagementSystem/1.0',
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($body) || $status !== 200) {
            $this->logger->warning('Open Library lookup failed', ['url' => $url, 'status' => $status, 'error' => $error]);
            throw new ApiException('Open Library is not reachable right now. Enter the details manually.', 502, 'LOOKUP_UNAVAILABLE');
        }
        $data = json_decode($body, true);

        return is_array($data) ? $data : [];
    }

    /** @return list<string> the trimmed `name` of each `{name: ...}` entry */
    private static function names(mixed $list): array
    {
        if (!is_array($list)) {
            return [];
        }
        $names = [];
        foreach ($list as $item) {
            if (is_array($item) && is_string($item['name'] ?? null) && trim($item['name']) !== '') {
                $names[] = trim($item['name']);
            }
        }

        return $names;
    }
}
