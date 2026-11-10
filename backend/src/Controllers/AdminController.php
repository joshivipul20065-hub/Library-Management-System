<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Http;
use App\Helpers\ListQuery;
use App\Repositories\AuditLogRepositoryInterface;
use App\Services\AuditService;
use App\Services\SettingsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Audit log viewer and circulation settings. */
final class AdminController
{
    public function __construct(private readonly AuditService $audit, private readonly SettingsService $settings)
    {
    }

    public function auditLogs(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, $this->audit->list(ListQuery::fromRequest($request, AuditLogRepositoryInterface::SORTABLE, 'created_at')));
    }

    public function settings(ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->settings->all()]);
    }

    public function updateSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return Http::json($response, ['data' => $this->settings->update(Http::body($request))]);
    }
}
