<?php

/**
 * Scheduled job (run hourly, e.g. `0 * * * * php /var/www/html/bin/mark_overdue.php`):
 *  - flags loans past their due date as overdue and notifies the borrowers,
 *  - expires reservation holds that were not collected in time and offers the copy to the next student.
 */

declare(strict_types=1);

use App\Helpers\RequestContext;
use App\Kernel;
use App\Services\CirculationService;
use App\Services\ReservationService;
use Psr\Log\LoggerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = Kernel::container();
$container->get(RequestContext::class)->ip = 'cli';
$logger = $container->get(LoggerInterface::class);

try {
    $overdue = $container->get(CirculationService::class)->processOverdue();
    $expired = $container->get(ReservationService::class)->expireStale();
    $logger->info('Scheduled job finished', ['newly_overdue' => $overdue, 'expired_reservations' => $expired]);
    echo sprintf("[%s] Newly overdue loans: %d, expired reservations: %d\n", gmdate('c'), $overdue, $expired);
} catch (Throwable $e) {
    $logger->error('Scheduled job failed', ['exception' => $e]);
    fwrite(STDERR, 'Scheduled job failed: ' . $e->getMessage() . "\n");
    exit(1);
}
