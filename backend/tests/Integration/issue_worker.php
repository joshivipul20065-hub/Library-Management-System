<?php

/**
 * Helper process for ConcurrentIssueTest: waits until a shared start time, then tries to issue a copy.
 * Prints "OK" on success or the API error code on rejection.
 * Usage: php issue_worker.php <studentId> <barcode> <unixStartTime>
 */

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Helpers\RequestContext;
use App\Kernel;
use App\Models\AuthUser;
use App\Services\CirculationService;

require dirname(__DIR__) . '/bootstrap.php';

[, $studentId, $barcode, $startAt] = $argv;

$container = Kernel::container();
$container->get(RequestContext::class)->user = new AuthUser(1, AuthUser::ROLE_ADMIN, 'Admin', 'admin@test.local', 'active', null);
$service = $container->get(CirculationService::class);
$container->get(App\Helpers\Database::class); // connect before the start line

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $service->issueCopy((int) $studentId, $barcode);
    echo 'OK';
} catch (ApiException $e) {
    echo $e->errorCode;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
}
