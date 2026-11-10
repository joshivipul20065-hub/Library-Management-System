<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Integration tests run against a dedicated database that is wiped between tests.
$_ENV['DB_NAME'] = getenv('DB_TEST_NAME') ?: 'library_test';
putenv('DB_NAME=' . $_ENV['DB_NAME']);
