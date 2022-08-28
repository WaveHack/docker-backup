<?php

namespace App\Exceptions;

use JetBrains\PhpStorm\Pure;
use RuntimeException;

class DockerVolumeNotFoundException extends RuntimeException
{
    #[Pure]
    public function __construct(string $volumeName)
    {
        parent::__construct("Docker volume '$volumeName' not found");
    }
}
