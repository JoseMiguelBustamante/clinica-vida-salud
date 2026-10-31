<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use RuntimeException;

final class TooManyLoginAttempts extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Too many login attempts. Try again later.');
    }
}
