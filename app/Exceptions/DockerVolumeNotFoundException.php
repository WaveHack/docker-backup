<?php

namespace App\Exceptions;

use JetBrains\PhpStorm\Pure;
use RuntimeException;

class DockerVolumeNotFoundException extends RuntimeException
{
    #[Pure]
    public function __construct(string $volume)
    {
        parent::__construct("Docker volume '$volume' not found");
    }
}
