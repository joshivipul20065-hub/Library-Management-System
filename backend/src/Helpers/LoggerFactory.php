<?php

declare(strict_types=1);

namespace App\Helpers;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public static function create(string $channel = 'api'): LoggerInterface
    {
        $level = Env::bool('APP_DEBUG') ? Level::Debug : Level::Info;
        $file = new RotatingFileHandler(dirname(__DIR__, 2) . '/logs/app.log', 14, $level, true, 0664);
        $file->setFormatter(new JsonFormatter());

        $logger = new Logger($channel);
        $logger->pushProcessor(new PsrLogMessageProcessor());
        // A broken log destination must never fail the request itself.
        $logger->pushHandler(new WhatFailureGroupHandler([$file]));
        $logger->pushHandler(new WhatFailureGroupHandler([new StreamHandler('php://stderr', Level::Warning)]));

        return $logger;
    }
}
