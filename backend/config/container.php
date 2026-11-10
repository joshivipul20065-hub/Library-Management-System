<?php

declare(strict_types=1);

use App\Helpers\Database;
use App\Helpers\Env;
use App\Helpers\JwtService;
use App\Helpers\LoggerFactory;
use App\Middleware\ErrorHandler;
use App\Repositories;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\UploadService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

use function DI\autowire;

return [
    LoggerInterface::class => static fn (): LoggerInterface => LoggerFactory::create(),

    Database::class => static fn (): Database => Database::connect(
        Env::string('DB_HOST', '127.0.0.1'),
        Env::int('DB_PORT', 3306),
        Env::string('DB_NAME', 'library'),
        Env::string('DB_USER', 'library'),
        Env::string('DB_PASS', ''),
    ),

    ResponseFactoryInterface::class => autowire(ResponseFactory::class),

    ErrorHandler::class => static fn (ContainerInterface $c): ErrorHandler => new ErrorHandler(
        $c->get(ResponseFactoryInterface::class),
        $c->get(LoggerInterface::class),
        Env::bool('APP_DEBUG'),
    ),

    JwtService::class => static fn (): JwtService => new JwtService(Env::required('JWT_SECRET'), Env::int('JWT_ACCESS_TTL', 900)),

    AuthService::class => autowire()
        ->constructorParameter('refreshSecret', Env::required('JWT_REFRESH_SECRET'))
        ->constructorParameter('refreshTtl', Env::int('JWT_REFRESH_TTL', 604800))
        ->constructorParameter('appUrl', Env::string('APP_URL', 'http://localhost:5173')),

    MailService::class => static fn (ContainerInterface $c): MailService => new MailService(
        $c->get(LoggerInterface::class),
        Env::get('SMTP_HOST'),
        Env::int('SMTP_PORT', 587),
        Env::get('SMTP_USER'),
        Env::get('SMTP_PASS'),
        Env::get('SMTP_SECURE'),
        Env::string('MAIL_FROM', 'library@library.test'),
        Env::string('MAIL_FROM_NAME', 'Library'),
    ),

    UploadService::class => autowire()->constructorParameter('directory', dirname(__DIR__) . '/public/uploads/covers'),

    Repositories\UserRepositoryInterface::class => autowire(Repositories\UserRepository::class),
    Repositories\TokenRepositoryInterface::class => autowire(Repositories\TokenRepository::class),
    Repositories\StudentRepositoryInterface::class => autowire(Repositories\StudentRepository::class),
    Repositories\BookRepositoryInterface::class => autowire(Repositories\BookRepository::class),
    Repositories\CategoryRepositoryInterface::class => autowire(Repositories\CategoryRepository::class),
    Repositories\AuthorRepositoryInterface::class => autowire(Repositories\AuthorRepository::class),
    Repositories\IssueRepositoryInterface::class => autowire(Repositories\IssueRepository::class),
    Repositories\ReservationRepositoryInterface::class => autowire(Repositories\ReservationRepository::class),
    Repositories\FineRepositoryInterface::class => autowire(Repositories\FineRepository::class),
    Repositories\NotificationRepositoryInterface::class => autowire(Repositories\NotificationRepository::class),
    Repositories\AuditLogRepositoryInterface::class => autowire(Repositories\AuditLogRepository::class),
    Repositories\SettingRepositoryInterface::class => autowire(Repositories\SettingRepository::class),
    Repositories\ReportRepositoryInterface::class => autowire(Repositories\ReportRepository::class),
];
