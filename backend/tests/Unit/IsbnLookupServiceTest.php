<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\IsbnLookupService;
use PHPUnit\Framework\TestCase;

final class IsbnLookupServiceTest extends TestCase
{
    public function testMapsOpenLibraryRecordToFormFields(): void
    {
        $payload = ['ISBN:9780141439518' => [
            'title' => ' Pride and Prejudice ',
            'authors' => [['name' => 'Jane Austen ', 'url' => 'https://openlibrary.org/authors/OL21594A']],
            'publishers' => [['name' => 'Penguin Books']],
            'publish_date' => 'April 29, 2003',
            'subjects' => [['name' => 'Fiction'], ['name' => '']],
            'cover' => ['medium' => 'https://covers.openlibrary.org/b/id/1-M.jpg', 'large' => 'https://covers.openlibrary.org/b/id/1-L.jpg'],
            'url' => 'https://openlibrary.org/books/OL1M/Pride_and_Prejudice',
        ]];

        self::assertSame([
            'isbn' => '9780141439518',
            'title' => 'Pride and Prejudice',
            'authors' => ['Jane Austen'],
            'publisher' => 'Penguin Books',
            'published_year' => 2003,
            'subjects' => ['Fiction'],
            'cover_url' => 'https://covers.openlibrary.org/b/id/1-L.jpg',
            'source_url' => 'https://openlibrary.org/books/OL1M/Pride_and_Prejudice',
        ], IsbnLookupService::map($payload, '9780141439518'));
    }

    public function testMissingFieldsBecomeNull(): void
    {
        $result = IsbnLookupService::map(['ISBN:9780735211292' => ['title' => 'Atomic Habits', 'publish_date' => 'n.d.']], '9780735211292');

        self::assertNotNull($result);
        self::assertSame([], $result['authors']);
        self::assertNull($result['publisher']);
        self::assertNull($result['published_year']);
        self::assertNull($result['cover_url']);
    }

    public function testCoverUrlFromSearchResult(): void
    {
        self::assertSame(
            'https://covers.openlibrary.org/b/id/12818862-M.jpg',
            IsbnLookupService::coverFromSearch(['numFound' => 3, 'docs' => [['cover_i' => 12818862]]])
        );
        self::assertSame(
            'https://covers.openlibrary.org/b/id/7-M.jpg',
            IsbnLookupService::coverFromSearch(['docs' => [['title' => 'no cover'], ['cover_i' => 7]]]),
            'Skips results without a cover'
        );
        self::assertNull(IsbnLookupService::coverFromSearch(['numFound' => 1, 'docs' => [[]]]));
        self::assertNull(IsbnLookupService::coverFromSearch(['numFound' => 0, 'docs' => []]));
    }

    public function testUnknownIsbnAndSpamRecordsAreNotFound(): void
    {
        self::assertNull(IsbnLookupService::map([], '9780132350884'));
        $spam = ['ISBN:9780132350884' => [
            'title' => 'The Earn2Trade Discount Code',
            'publishers' => [['name' => 'The Earn2Trade Discount Code']],
            'publish_date' => '2026',
        ]];
        self::assertNull(IsbnLookupService::map($spam, '9780132350884'));
    }
}
