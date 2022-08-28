<?php

namespace App\Exceptions;

use JetBrains\PhpStorm\Pure;
use RuntimeException;

class DockerContainerNotFoundException extends RuntimeException
{
    #[Pure]
    public function __construct(string $container)
    {
        parent::__construct("Docker container '$container' not found");
    }
}
