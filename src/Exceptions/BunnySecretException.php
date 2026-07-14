<?php

namespace Novay\BunnySecret\Exceptions;

use RuntimeException;

class BunnySecretException extends RuntimeException
{
    public static function missingConfig(string $key): self
    {
        return new self("BunnyCDN configuration [{$key}] is missing.");
    }
}
