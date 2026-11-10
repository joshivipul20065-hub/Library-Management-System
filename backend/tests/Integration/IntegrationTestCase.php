<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Helpers\Clock;
use App\Helpers\Database;
use App\Helpers\Password;
use App\Helpers\RequestContext;
use App\Kernel;
use App\Models\AuthUser;
use DI\Container;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real MySQL test database (DB_TEST_NAME). The schema is loaded once per class
 * and every table is truncated before each test.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected const TODAY = '2026-03-01';

    protected static Database $db;
    private static bool $schemaLoaded = false;
    private static ?string $passwordHash = null;

    public static function setUpBeforeClass(): void
    {
        self::$db = Kernel::container()->get(Database::class);
        if (!self::$schemaLoaded) {
            $pdo = self::$db->pdo();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (self::$db->all('SHOW TABLES') as $row) {
                $pdo->exec('DROP TABLE `' . (string) array_values($row)[0] . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $pdo->exec((string) file_get_contents(dirname(__DIR__, 3) . '/database/schema.sql'));
            self::$schemaLoaded = true;
        }
    }

    protected function setUp(): void
    {
        $pdo = self::$db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::$db->all('SHOW TABLES') as $row) {
            $table = (string) array_values($row)[0];
            if ($table !== 'schema_migrations') {
                $pdo->exec("TRUNCATE TABLE `{$table}`");
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        self::$db->insert(
            "INSERT INTO users (id, role, name, email, password_hash) VALUES (1, 'admin', 'Admin', 'admin@test.local', :hash)",
            ['hash' => self::hash()]
        );
    }

    /** A container whose clock is fixed to $date and whose current user is the admin (or $actor). */
    protected function app(string $date = self::TODAY, ?AuthUser $actor = null): Container
    {
        $container = Kernel::container([Clock::class => new Clock($date . ' 10:00:00')]);
        $container->get(RequestContext::class)->user = $actor ?? new AuthUser(1, AuthUser::ROLE_ADMIN, 'Admin', 'admin@test.local', 'active', null);

        return $container;
    }

    protected function studentActor(int $studentId): AuthUser
    {
        $row = self::$db->one('SELECT u.* FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = :id', ['id' => $studentId]);
        self::assertNotNull($row);

        return new AuthUser((int) $row['id'], AuthUser::ROLE_STUDENT, (string) $row['name'], (string) $row['email'], 'active', $studentId);
    }

    protected function createStudent(string $roll): int
    {
        $userId = self::$db->insert(
            "INSERT INTO users (role, name, email, password_hash) VALUES ('student', :name, :email, :hash)",
            ['name' => "Student {$roll}", 'email' => strtolower($roll) . '@test.local', 'hash' => self::hash()]
        );

        return self::$db->insert(
            "INSERT INTO students (user_id, roll_no, department, year) VALUES (:user, :roll, 'Computer Science', 2)",
            ['user' => $userId, 'roll' => $roll]
        );
    }

    /**
     * Creates a book with the given number of copies; barcodes are "{$code}-1", "{$code}-2", ...
     *
     * @return array{0: int, 1: list<string>}
     */
    protected function createBook(string $code, int $copies = 1): array
    {
        $bookId = self::$db->insert(
            'INSERT INTO books (isbn, title, total_copies, available_copies) VALUES (:isbn, :title, :total, :available)',
            ['isbn' => str_pad((string) crc32($code), 13, '0', STR_PAD_LEFT), 'title' => "Book {$code}", 'total' => $copies, 'available' => $copies]
        );
        $barcodes = [];
        for ($i = 1; $i <= $copies; $i++) {
            $barcodes[] = "{$code}-{$i}";
            self::$db->insert('INSERT INTO book_copies (book_id, barcode) VALUES (:book, :barcode)', ['book' => $bookId, 'barcode' => "{$code}-{$i}"]);
        }

        return [$bookId, $barcodes];
    }

    protected function availableCopies(int $bookId): int
    {
        return (int) self::$db->scalar('SELECT available_copies FROM books WHERE id = :id', ['id' => $bookId]);
    }

    private static function hash(): string
    {
        return self::$passwordHash ??= Password::hash('Secret123');
    }
}
