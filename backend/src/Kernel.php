<?php

declare(strict_types=1);

namespace App;

use App\Helpers\Env;
use App\Helpers\Http;
use App\Middleware\ErrorHandler;
use App\Middleware\JsonBodyMiddleware;
use App\Middleware\RequestLogMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use DI\Bridge\Slim\Bridge;
use DI\Container;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\App;
use Tuupola\Middleware\CorsMiddleware;

final class Kernel
{
    /** @param array<string, mixed> $overrides container definitions that replace the defaults (tests) */
    public static function container(array $overrides = []): Container
    {
        Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
        self::assertSecrets();

        $builder = new ContainerBuilder();
        $builder->addDefinitions(dirname(__DIR__) . '/config/container.php');
        if ($overrides !== []) {
            $builder->addDefinitions($overrides);
        }

        return $builder->build();
    }

    /** @return App<ContainerInterface|null> */
    public static function app(?Container $container = null): App
    {
        $container ??= self::container();
        $app = Bridge::create($container);

        (require dirname(__DIR__) . '/config/routes.php')($app);

        // Middleware runs outermost-last: RequestLog -> CORS -> SecurityHeaders -> Error -> JsonBody -> Routing.
        $app->addRoutingMiddleware();
        $app->add(JsonBodyMiddleware::class);
        $errorMiddleware = $app->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler($container->get(ErrorHandler::class));
        $app->add(SecurityHeadersMiddleware::class);
        $app->add(new CorsMiddleware([
            'origin' => array_map('trim', explode(',', Env::string('CORS_ORIGIN', 'http://localhost:5173'))),
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'headers.allow' => ['Authorization', 'Content-Type', 'X-Requested-With', 'Accept'],
            'headers.expose' => ['Content-Disposition', 'X-RateLimit-Remaining'],
            'credentials' => true,
            'cache' => 600,
            // Explain rejected origins instead of the library's default empty 401.
            'error' => fn (ServerRequestInterface $request, ResponseInterface $response, ?array $arguments): ResponseInterface => Http::json(
                $response,
                ['error' => [
                    'code' => 'CORS_ORIGIN_NOT_ALLOWED',
                    'message' => 'This site is not allowed to call the API. Add its address to CORS_ORIGIN.',
                    'details' => (object) ['origin' => $request->getHeaderLine('Origin'), 'reason' => $arguments['message'] ?? null],
                ]],
                403
            ),
        ]));
        $app->add(RequestLogMiddleware::class);

        return $app;
    }

    private static function assertSecrets(): void
    {
        foreach (['JWT_SECRET', 'JWT_REFRESH_SECRET'] as $key) {
            $value = Env::required($key);
            if (strlen($value) < 32 || str_contains($value, 'change_me')) {
                if (Env::isProduction()) {
                    throw new RuntimeException("{$key} must be a random string of at least 32 characters");
                }
                error_log("WARNING: {$key} is weak; generate one with `openssl rand -hex 32`.");
            }
        }
    }
}
