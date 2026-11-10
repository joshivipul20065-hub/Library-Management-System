<?php

/**
 * Generates database/seed.sql. Passwords are hashed here with Argon2id, so no plain-text password is stored.
 * Dates are written relative to CURDATE(), so the sample data stays current whenever it is loaded.
 *
 * Usage: php bin/generate_seed.php [--covers] > ../database/seed.sql
 *   --covers  look up each book's cover on Open Library by title + author (about 2 minutes; needs internet)
 *
 * Default logins: admin@library.test / Admin@123, librarian@library.test / Librarian@123,
 *                 student1@library.test ... student20@library.test / Student@123
 */

declare(strict_types=1);

use App\Helpers\Password;
use App\Services\IsbnLookupService;
use Psr\Log\NullLogger;

require dirname(__DIR__) . '/vendor/autoload.php';

mt_srand(20260101);

$openLibrary = in_array('--covers', $argv, true) ? new IsbnLookupService(new NullLogger()) : null;
$findCover = static function (string $title, string $author) use ($openLibrary): ?string {
    if ($openLibrary === null) {
        return null;
    }
    $url = null;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        try {
            $url = $openLibrary->findCoverUrl($title, $author);
            break;
        } catch (Throwable $e) {
            sleep($attempt); // transient Open Library errors/timeouts: back off and retry
        }
    }
    fwrite(STDERR, sprintf("%-55s %s\n", mb_substr($title, 0, 55), $url ?? '(no cover)'));
    usleep(200_000);

    return $url;
};

/** SQL string literal for trusted, static seed content. */
function q(?string $value): string
{
    return $value === null ? 'NULL' : "'" . str_replace(['\\', "'"], ['\\\\', "''"], $value) . "'";
}

function daysAgo(int $days): string
{
    return $days === 0 ? 'CURDATE()' : "CURDATE() - INTERVAL {$days} DAY";
}

function isbn13(int $n): string
{
    $digits = '978930' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    $sum = 0;
    foreach (str_split($digits) as $i => $digit) {
        $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
    }

    return $digits . ((10 - $sum % 10) % 10);
}

// category => [code, description, [[title, authors (;-separated), year|null, publisher], ...]]
$catalog = [
    'Computer Science' => ['CS', 'Programming, algorithms and software engineering', [
        ['Clean Code', 'Robert C. Martin', 2008, 'Prentice Hall'],
        ['The Pragmatic Programmer', 'Andrew Hunt;David Thomas', 1999, 'Addison-Wesley'],
        ['Introduction to Algorithms', 'Thomas H. Cormen;Charles E. Leiserson;Ronald L. Rivest;Clifford Stein', 2009, 'MIT Press'],
        ['Design Patterns', 'Erich Gamma;Richard Helm;Ralph Johnson;John Vlissides', 1994, 'Addison-Wesley'],
        ['Structure and Interpretation of Computer Programs', 'Harold Abelson;Gerald Jay Sussman', 1996, 'MIT Press'],
        ['The C Programming Language', 'Brian W. Kernighan;Dennis M. Ritchie', 1988, 'Prentice Hall'],
        ['Code Complete', 'Steve McConnell', 2004, 'Microsoft Press'],
        ['Refactoring', 'Martin Fowler', 2018, 'Addison-Wesley'],
        ['Designing Data-Intensive Applications', 'Martin Kleppmann', 2017, "O'Reilly Media"],
        ['Artificial Intelligence: A Modern Approach', 'Stuart Russell;Peter Norvig', 2020, 'Pearson'],
    ]],
    'Fiction' => ['FIC', 'Novels and literary fiction', [
        ['Pride and Prejudice', 'Jane Austen', 1813, 'T. Egerton'],
        ['1984', 'George Orwell', 1949, 'Secker & Warburg'],
        ['To Kill a Mockingbird', 'Harper Lee', 1960, 'J. B. Lippincott'],
        ['The Great Gatsby', 'F. Scott Fitzgerald', 1925, 'Scribner'],
        ['One Hundred Years of Solitude', 'Gabriel García Márquez', 1967, 'Harper & Row'],
        ['The God of Small Things', 'Arundhati Roy', 1997, 'IndiaInk'],
        ["Midnight's Children", 'Salman Rushdie', 1981, 'Jonathan Cape'],
        ['The Alchemist', 'Paulo Coelho', 1988, 'HarperTorch'],
        ['Crime and Punishment', 'Fyodor Dostoevsky', 1866, 'The Russian Messenger'],
        ['The Catcher in the Rye', 'J. D. Salinger', 1951, 'Little, Brown'],
    ]],
    'Science' => ['SCI', 'Popular and foundational science', [
        ['A Brief History of Time', 'Stephen Hawking', 1988, 'Bantam'],
        ['The Selfish Gene', 'Richard Dawkins', 1976, 'Oxford University Press'],
        ['Cosmos', 'Carl Sagan', 1980, 'Random House'],
        ['On the Origin of Species', 'Charles Darwin', 1859, 'John Murray'],
        ['The Double Helix', 'James D. Watson', 1968, 'Atheneum'],
        ['The Elegant Universe', 'Brian Greene', 1999, 'W. W. Norton'],
        ['Silent Spring', 'Rachel Carson', 1962, 'Houghton Mifflin'],
        ['The Gene: An Intimate History', 'Siddhartha Mukherjee', 2016, 'Scribner'],
        ['The Feynman Lectures on Physics', 'Richard P. Feynman;Robert B. Leighton;Matthew Sands', 1964, 'Addison-Wesley'],
        ['Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', 2011, 'Harper'],
    ]],
    'Mathematics' => ['MAT', 'Pure and applied mathematics', [
        ['Calculus', 'Michael Spivak', 1967, 'Publish or Perish'],
        ['Linear Algebra Done Right', 'Sheldon Axler', 1995, 'Springer'],
        ['Concrete Mathematics', 'Ronald L. Graham;Donald E. Knuth;Oren Patashnik', 1989, 'Addison-Wesley'],
        ['How to Solve It', 'George Pólya', 1945, 'Princeton University Press'],
        ['Principles of Mathematical Analysis', 'Walter Rudin', 1953, 'McGraw-Hill'],
        ['Introduction to Probability', 'Joseph K. Blitzstein;Jessica Hwang', 2014, 'CRC Press'],
        ['Discrete Mathematics and Its Applications', 'Kenneth H. Rosen', 1988, 'McGraw-Hill'],
        ['Gödel, Escher, Bach', 'Douglas Hofstadter', 1979, 'Basic Books'],
        ['The Art of Computer Programming, Vol. 1', 'Donald E. Knuth', 1968, 'Addison-Wesley'],
        ['Higher Engineering Mathematics', 'B. S. Grewal', 1965, 'Khanna Publishers'],
    ]],
    'History' => ['HIS', 'World and Indian history', [
        ['The Discovery of India', 'Jawaharlal Nehru', 1946, 'Signet Press'],
        ['India After Gandhi', 'Ramachandra Guha', 2007, 'Picador'],
        ['Guns, Germs, and Steel', 'Jared Diamond', 1997, 'W. W. Norton'],
        ['The Silk Roads', 'Peter Frankopan', 2015, 'Bloomsbury'],
        ["A People's History of the United States", 'Howard Zinn', 1980, 'Harper & Row'],
        ['The Rise and Fall of the Third Reich', 'William L. Shirer', 1960, 'Simon & Schuster'],
        ['SPQR: A History of Ancient Rome', 'Mary Beard', 2015, 'Profile Books'],
        ['The Wonder That Was India', 'A. L. Basham', 1954, 'Sidgwick & Jackson'],
        ['Freedom at Midnight', 'Larry Collins;Dominique Lapierre', 1975, 'Simon & Schuster'],
        ['The Guns of August', 'Barbara W. Tuchman', 1962, 'Macmillan'],
    ]],
    'Business & Economics' => ['BUS', 'Economics, finance and management', [
        ['The Wealth of Nations', 'Adam Smith', 1776, 'W. Strahan'],
        ['Thinking, Fast and Slow', 'Daniel Kahneman', 2011, 'Farrar, Straus and Giroux'],
        ['Good to Great', 'Jim Collins', 2001, 'HarperBusiness'],
        ['The Lean Startup', 'Eric Ries', 2011, 'Crown Business'],
        ['Zero to One', 'Peter Thiel;Blake Masters', 2014, 'Crown Business'],
        ['Freakonomics', 'Steven D. Levitt;Stephen J. Dubner', 2005, 'William Morrow'],
        ['The Intelligent Investor', 'Benjamin Graham', 1949, 'Harper & Brothers'],
        ['Capital in the Twenty-First Century', 'Thomas Piketty', 2013, 'Belknap Press'],
        ["The Innovator's Dilemma", 'Clayton M. Christensen', 1997, 'Harvard Business Review Press'],
        ['Poor Economics', 'Abhijit V. Banerjee;Esther Duflo', 2011, 'PublicAffairs'],
    ]],
    'Philosophy' => ['PHI', 'Classical and modern philosophy', [
        ['Meditations', 'Marcus Aurelius', null, 'Penguin Classics'],
        ['The Republic', 'Plato', null, 'Penguin Classics'],
        ['Beyond Good and Evil', 'Friedrich Nietzsche', 1886, 'C. G. Naumann'],
        ['The Bhagavad Gita', 'Vyasa', null, 'Penguin Classics'],
        ['Critique of Pure Reason', 'Immanuel Kant', 1781, 'J. F. Hartknoch'],
        ["Sophie's World", 'Jostein Gaarder', 1991, 'Aschehoug'],
        ['The Myth of Sisyphus', 'Albert Camus', 1942, 'Gallimard'],
        ["Man's Search for Meaning", 'Viktor E. Frankl', 1946, 'Beacon Press'],
        ['Being and Time', 'Martin Heidegger', 1927, 'Max Niemeyer Verlag'],
        ['Tao Te Ching', 'Laozi', null, 'Penguin Classics'],
    ]],
    'Engineering' => ['ENG', 'Core engineering textbooks', [
        ['Engineering Mechanics: Statics', 'J. L. Meriam;L. G. Kraige', 1951, 'Wiley'],
        ['Fundamentals of Electric Circuits', 'Charles K. Alexander;Matthew N. O. Sadiku', 2000, 'McGraw-Hill'],
        ['Thermodynamics: An Engineering Approach', 'Yunus A. Çengel;Michael A. Boles', 1989, 'McGraw-Hill'],
        ['Microelectronic Circuits', 'Adel S. Sedra;Kenneth C. Smith', 1982, 'Oxford University Press'],
        ['Strength of Materials', 'R. K. Bansal', 2010, 'Laxmi Publications'],
        ['Signals and Systems', 'Alan V. Oppenheim;Alan S. Willsky', 1983, 'Prentice Hall'],
        ['Fluid Mechanics', 'Frank M. White', 1979, 'McGraw-Hill'],
        ['Digital Design', 'M. Morris Mano', 1984, 'Prentice Hall'],
        ['Control Systems Engineering', 'Norman S. Nise', 1992, 'Wiley'],
        ["Shigley's Mechanical Engineering Design", 'Richard G. Budynas;J. Keith Nisbett', 1963, 'McGraw-Hill'],
    ]],
    'Self-Help' => ['SLF', 'Personal development and psychology', [
        ['Atomic Habits', 'James Clear', 2018, 'Avery'],
        ['The 7 Habits of Highly Effective People', 'Stephen R. Covey', 1989, 'Free Press'],
        ['How to Win Friends and Influence People', 'Dale Carnegie', 1936, 'Simon & Schuster'],
        ['Deep Work', 'Cal Newport', 2016, 'Grand Central Publishing'],
        ['Mindset', 'Carol S. Dweck', 2006, 'Random House'],
        ['Wings of Fire', 'A. P. J. Abdul Kalam;Arun Tiwari', 1999, 'Universities Press'],
        ['The Power of Habit', 'Charles Duhigg', 2012, 'Random House'],
        ['Ikigai', 'Héctor García;Francesc Miralles', 2016, 'Penguin'],
        ['Grit', 'Angela Duckworth', 2016, 'Scribner'],
        ['Quiet', 'Susan Cain', 2012, 'Crown'],
    ]],
    "Children's Books" => ['CHI', 'Stories for young readers', [
        ["Charlotte's Web", 'E. B. White', 1952, 'Harper & Brothers'],
        ['The Little Prince', 'Antoine de Saint-Exupéry', 1943, 'Reynal & Hitchcock'],
        ['Matilda', 'Roald Dahl', 1988, 'Jonathan Cape'],
        ['Malgudi Days', 'R. K. Narayan', 1943, 'Indian Thought Publications'],
        ['The Jungle Book', 'Rudyard Kipling', 1894, 'Macmillan'],
        ["Alice's Adventures in Wonderland", 'Lewis Carroll', 1865, 'Macmillan'],
        ['Panchatantra', 'Vishnu Sharma', null, 'Rupa Publications'],
        ['The Hobbit', 'J. R. R. Tolkien', 1937, 'George Allen & Unwin'],
        ['Wonder', 'R. J. Palacio', 2012, 'Alfred A. Knopf'],
        ['The Blue Umbrella', 'Ruskin Bond', 1980, 'Rupa Publications'],
    ]],
];

$studentNames = [
    'Aarav Sharma', 'Priya Patel', 'Rohan Gupta', 'Ananya Iyer', 'Vikram Singh', 'Sneha Reddy', 'Arjun Nair',
    'Kavya Menon', 'Rahul Verma', 'Isha Joshi', 'Aditya Kulkarni', 'Meera Pillai', 'Siddharth Rao', 'Neha Deshpande',
    'Karan Malhotra', 'Pooja Chatterjee', 'Nikhil Bhat', 'Divya Krishnan', 'Varun Saxena', 'Tanvi Mehta',
];
$departments = ['Computer Science', 'Electronics', 'Mechanical', 'Civil', 'Commerce', 'Arts'];

$out = [];
$out[] = '-- Generated by backend/bin/generate_seed.php. Do not edit by hand; re-run the generator instead.';
$out[] = "-- Logins: admin@library.test / Admin@123, librarian@library.test / Librarian@123, student1..20@library.test / Student@123";
$out[] = 'SET NAMES utf8mb4;';
$out[] = 'SET FOREIGN_KEY_CHECKS = 0;';
$out[] = '';

// Users and students
$users = [
    [1, 'admin', 'Library Administrator', 'admin@library.test', Password::hash('Admin@123')],
    [2, 'librarian', 'Lata Librarian', 'librarian@library.test', Password::hash('Librarian@123')],
];
$studentHash = Password::hash('Student@123');
$studentRows = [];
foreach ($studentNames as $i => $name) {
    $n = $i + 1;
    $userId = $n + 2;
    $users[] = [$userId, 'student', $name, "student{$n}@library.test", $studentHash];
    $department = $departments[$i % count($departments)];
    $roll = strtoupper(substr(str_replace(' ', '', $department), 0, 3)) . '2026-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    $studentRows[] = sprintf(
        '(%d, %d, %s, %s, %d, %s, %s)',
        $n,
        $userId,
        q($roll),
        q($department),
        ($i % 4) + 1,
        q('+91 98' . str_pad((string) (45000000 + $n * 7919), 8, '0', STR_PAD_LEFT)),
        q(($n * 3) . ' College Road, Pune')
    );
}
$out[] = 'INSERT INTO users (id, role, name, email, password_hash, last_login_at) VALUES';
$out[] = implode(",\n", array_map(
    static fn (array $u): string => sprintf('(%d, %s, %s, %s, %s, %s)', $u[0], q($u[1]), q($u[2]), q($u[3]), q($u[4]), 'NULL'),
    $users
)) . ';';
$out[] = 'INSERT INTO students (id, user_id, roll_no, department, year, phone, address) VALUES';
$out[] = implode(",\n", $studentRows) . ';';
$out[] = '';

// Catalogue
$categoryRows = [];
$authorIds = [];
$bookRows = [];
$bookAuthorRows = [];
$copies = []; // [copyId, bookId]
$copiesPerBook = [];
$bookId = 0;
$categoryId = 0;
foreach ($catalog as $categoryName => [$code, $description, $books]) {
    $categoryId++;
    $categoryRows[] = sprintf('(%d, %s, %s)', $categoryId, q($categoryName), q($description));
    foreach ($books as $j => [$title, $authors, $year, $publisher]) {
        $bookId++;
        $names = explode(';', $authors);
        foreach ($names as $authorName) {
            $authorIds[$authorName] ??= count($authorIds) + 1;
            $bookAuthorRows[] = sprintf('(%d, %d)', $bookId, $authorIds[$authorName]);
        }
        $count = mt_rand(2, 4);
        $copiesPerBook[$bookId] = $count;
        for ($c = 1; $c <= $count; $c++) {
            $copies[] = [count($copies) + 1, $bookId];
        }
        $bookRows[$bookId] = [
            'isbn' => isbn13($bookId),
            'title' => $title,
            'publisher' => $publisher,
            'year' => $year,
            'category' => $categoryId,
            'description' => sprintf(
                '%s by %s%s.',
                $title,
                implode(', ', $names),
                $year === null ? '' : ", first published in {$year}"
            ),
            'shelf' => sprintf('%s-%s%d', $code, chr(65 + intdiv($j, 4)), $j % 4 + 1),
            'cover' => $findCover($title, $names[0]),
        ];
    }
}
$out[] = 'INSERT INTO categories (id, name, description) VALUES';
$out[] = implode(",\n", $categoryRows) . ';';
$out[] = 'INSERT INTO authors (id, name) VALUES';
$out[] = implode(",\n", array_map(static fn (string $name, int $id): string => sprintf('(%d, %s)', $id, q($name)), array_keys($authorIds), $authorIds)) . ';';

// Circulation history
$issues = [];
$fines = [];
$activeCopies = [];
$activeByStudent = array_fill(1, 20, []);
$shuffled = $copies;
shuffle($shuffled);
$pickActiveCopy = static function (int $student) use (&$shuffled, &$activeCopies, &$activeByStudent): ?array {
    foreach ($shuffled as $index => [$copyId, $book]) {
        if (!isset($activeCopies[$copyId]) && !in_array($book, $activeByStudent[$student], true)) {
            unset($shuffled[$index]);
            $activeCopies[$copyId] = $book;
            $activeByStudent[$student][] = $book;

            return [$copyId, $book];
        }
    }

    return null;
};

$issueId = 0;
// Returned on time
for ($k = 0; $k < 40; $k++) {
    [$copyId] = $copies[mt_rand(0, count($copies) - 1)];
    $ago = mt_rand(20, 170);
    $issues[] = [++$issueId, $copyId, mt_rand(1, 20), $ago, $ago - 14, $ago - mt_rand(2, 13), 'returned', mt_rand(0, 1)];
}
// Returned late, with fines (paid, unpaid for students 19/20, waived)
$lateStatuses = ['paid', 'paid', 'paid', 'paid', 'unpaid', 'unpaid', 'waived'];
foreach ($lateStatuses as $k => $fineStatus) {
    [$copyId] = $copies[mt_rand(0, count($copies) - 1)];
    $student = $fineStatus === 'unpaid' ? 19 + ($k % 2) : mt_rand(2, 18);
    $ago = mt_rand(40, 150);
    $late = mt_rand(2, 9);
    $returnedAgo = $ago - 14 - $late;
    $issues[] = [++$issueId, $copyId, $student, $ago, $ago - 14, $returnedAgo, 'returned', 0];
    $fines[] = [$issueId, $late * 5, sprintf('Overdue by %d day(s)', $late), $fineStatus, $returnedAgo];
}
// Active loans: student 1 (demo account) gets one due soon and one overdue
$plan = [[1, 12], [1, 4], [1, 21]];
for ($s = 2; $s <= 20; $s++) {
    $plan[] = [$s, mt_rand(0, 10)];
    if ($s % 3 === 0) {
        $plan[] = [$s, mt_rand(17, 30)];
    }
}
foreach ($plan as [$student, $ago]) {
    $pick = $pickActiveCopy($student);
    if ($pick === null) {
        continue;
    }
    $dueAgo = $ago - 14;
    $issues[] = [++$issueId, $pick[0], $student, $ago, $dueAgo, null, $dueAgo > 0 ? 'overdue' : 'issued', 0];
}

$out[] = 'INSERT INTO books (id, isbn, title, publisher, published_year, category_id, language, description, shelf_location,'
    . ' cover_url, total_copies, available_copies) VALUES';
$activePerBook = array_count_values($activeCopies);
$out[] = implode(",\n", array_map(
    static fn (int $id, array $b): string => sprintf(
        "(%d, %s, %s, %s, %s, %d, 'English', %s, %s, %s, %d, %d)",
        $id,
        q($b['isbn']),
        q($b['title']),
        q($b['publisher']),
        $b['year'] === null ? 'NULL' : (string) $b['year'],
        $b['category'],
        q($b['description']),
        q($b['shelf']),
        q($b['cover']),
        $copiesPerBook[$id],
        $copiesPerBook[$id] - ($activePerBook[$id] ?? 0)
    ),
    array_keys($bookRows),
    $bookRows
)) . ';';
$out[] = 'INSERT INTO book_authors (book_id, author_id) VALUES';
$out[] = implode(",\n", array_unique($bookAuthorRows)) . ';';
$copyCounter = [];
$out[] = 'INSERT INTO book_copies (id, book_id, barcode, status) VALUES';
$out[] = implode(",\n", array_map(
    static function (array $copy) use (&$copyCounter, $activeCopies): string {
        [$copyId, $book] = $copy;
        $copyCounter[$book] = ($copyCounter[$book] ?? 0) + 1;

        return sprintf(
            "(%d, %d, %s, '%s')",
            $copyId,
            $book,
            q(sprintf('LIB-%05d-%03d', $book, $copyCounter[$book])),
            isset($activeCopies[$copyId]) ? 'issued' : 'available'
        );
    },
    $copies
)) . ';';
$out[] = '';

$out[] = 'INSERT INTO issues (id, copy_id, student_id, issued_by, issue_date, due_date, return_date, status, renew_count, created_at) VALUES';
$out[] = implode(",\n", array_map(
    static fn (array $i): string => sprintf(
        "(%d, %d, %d, %d, %s, %s, %s, '%s', %d, %s)",
        $i[0],
        $i[1],
        $i[2],
        mt_rand(1, 2),
        daysAgo($i[3]),
        $i[4] >= 0 ? daysAgo($i[4]) : 'CURDATE() + INTERVAL ' . (-$i[4]) . ' DAY',
        $i[5] === null ? 'NULL' : daysAgo($i[5]),
        $i[6],
        $i[7],
        daysAgo($i[3])
    ),
    $issues
)) . ';';
$out[] = 'INSERT INTO fines (issue_id, amount, reason, status, paid_at, processed_by, created_at) VALUES';
$out[] = implode(",\n", array_map(
    static fn (array $f): string => sprintf(
        '(%d, %.2f, %s, %s, %s, %s, %s)',
        $f[0],
        $f[1],
        q($f[2]),
        q($f[3]),
        $f[3] === 'unpaid' ? 'NULL' : daysAgo(max(0, $f[4] - 1)),
        $f[3] === 'unpaid' ? 'NULL' : '2',
        daysAgo($f[4])
    ),
    $fines
)) . ';';

// Pending reservations on books with no copies left on the shelf
$reservations = [];
$fullyIssued = array_keys(array_filter($activePerBook, static fn (int $active, int $book): bool => $active >= $copiesPerBook[$book], ARRAY_FILTER_USE_BOTH));
foreach (array_slice($fullyIssued, 0, 3) as $k => $book) {
    foreach ([5, 6, 7, 8] as $candidate) {
        if (!in_array($book, $activeByStudent[$candidate], true)) {
            $reservations[] = sprintf("(%d, %d, 'pending', %s)", $book, $candidate, daysAgo($k + 1));
            break;
        }
    }
}
if ($reservations !== []) {
    $out[] = 'INSERT INTO reservations (book_id, student_id, status, created_at) VALUES';
    $out[] = implode(",\n", $reservations) . ';';
}

$out[] = "INSERT INTO notifications (user_id, title, body) VALUES
(3, 'Welcome to the library', 'Your account is ready. Browse the catalogue and reserve books online.'),
(3, 'Book overdue', 'One of your books is overdue. Please return it to avoid further fines.');";
$out[] = '';
$out[] = 'SET FOREIGN_KEY_CHECKS = 1;';

echo implode("\n", $out) . "\n";
