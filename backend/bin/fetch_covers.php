<?php

/**
 * Fills in missing book covers from Open Library (free, no key), matching each book by title and first author.
 * Books that already have a cover are left alone, so it is safe to re-run (e.g. after a CSV import).
 * The stored URL points at covers.openlibrary.org; images are not downloaded.
 *
 * Usage: php bin/fetch_covers.php [--limit=N]
 */

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Helpers\Database;
use App\Kernel;
use App\Services\IsbnLookupService;

require dirname(__DIR__) . '/vendor/autoload.php';

$limit = (int) (getopt('', ['limit:'])['limit'] ?? 1000);
$container = Kernel::container();
$db = $container->get(Database::class);
$openLibrary = $container->get(IsbnLookupService::class);

$books = $db->all(
    'SELECT b.id, b.title,
        (SELECT a.name FROM book_authors ba JOIN authors a ON a.id = ba.author_id WHERE ba.book_id = b.id ORDER BY a.id LIMIT 1) AS author
     FROM books b WHERE b.deleted_at IS NULL AND b.cover_url IS NULL ORDER BY b.id LIMIT ' . max(1, $limit)
);

$found = 0;
foreach ($books as $i => $book) {
    $label = sprintf('[%d/%d] %s', $i + 1, count($books), $book['title']);
    if (!is_string($book['author'])) {
        echo "{$label}: skipped (no author)\n";
        continue;
    }
    try {
        $url = $openLibrary->findCoverUrl((string) $book['title'], $book['author']);
    } catch (ApiException $e) {
        echo "{$label}: {$e->getMessage()}\n";
        continue;
    }
    if ($url === null) {
        echo "{$label}: no cover found\n";
        continue;
    }
    $db->execute('UPDATE books SET cover_url = :url WHERE id = :id AND cover_url IS NULL', ['url' => $url, 'id' => $book['id']]);
    $found++;
    echo "{$label}: {$url}\n";
    usleep(200_000); // be polite to the free API
}

echo sprintf("Done: %d of %d books got a cover.\n", $found, count($books));
