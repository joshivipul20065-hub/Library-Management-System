<?php

/**
 * Loads database/schema.sql into an empty database, then applies database/migrations/*.sql in order.
 * Usage: php bin/migrate.php
 */

declare(strict_types=1);

use App\Helpers\Database;
use App\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = Kernel::container();
$databaseDir = dirname(__DIR__, 2) . '/database';

$db = null;
for ($attempt = 1; $attempt <= 30; $attempt++) {
    try {
        $db = $container->get(Database::class);
        break;
    } catch (Throwable $e) {
        fwrite(STDERR, "Waiting for database ({$attempt}/30): {$e->getMessage()}\n");
        sleep(2);
    }
}
if (!$db instanceof Database) {
    fwrite(STDERR, "Could not connect to the database.\n");
    exit(1);
}

$hasSchema = (int) $db->scalar(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'"
) > 0;
if (!$hasSchema) {
    $db->pdo()->exec((string) file_get_contents($databaseDir . '/schema.sql'));
    echo "Loaded baseline schema.\n";
}

$applied = array_map(static fn (array $row): string => (string) $row['version'], $db->all('SELECT version FROM schema_migrations'));
$files = glob($databaseDir . '/migrations/*.sql') ?: [];
sort($files);
foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (in_array($version, $applied, true)) {
        continue;
    }
    $db->pdo()->exec((string) file_get_contents($file));
    $db->insert('INSERT INTO schema_migrations (version) VALUES (:version)', ['version' => $version]);
    echo "Applied migration {$version}.\n";
}

echo "Database is up to date.\n";
