<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\AuthenticationException;
use App\Models\AuthUser;

/** Per-request state (authenticated user, client IP) populated by middleware. */
final class RequestContext
{
    public ?AuthUser $user = null;
    public string $ip = '0.0.0.0';

    public function user(): AuthUser
    {
        return $this->user ?? throw new AuthenticationException();
    }
}
