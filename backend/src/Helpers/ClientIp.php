<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ServerRequestInterface;

final class ClientIp
{
    /** Uses X-Forwarded-For only when TRUST_PROXY is enabled and the direct peer is a private address. */
    public static function resolve(ServerRequestInterface $request): string
    {
        $remote = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!Env::bool('TRUST_PROXY') || filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return $remote;
        }
        $forwarded = trim(explode(',', $request->getHeaderLine('X-Forwarded-For'))[0]);

        return filter_var($forwarded, FILTER_VALIDATE_IP) !== false ? $forwarded : $remote;
    }
}
