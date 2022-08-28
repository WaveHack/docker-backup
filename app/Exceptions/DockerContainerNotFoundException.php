<?php

namespace App\Exceptions;

use JetBrains\PhpStorm\Pure;
use RuntimeException;

class DockerContainerNotFoundException extends RuntimeException
{
    #[Pure]
    public function __construct(string $containerName)
    {
        parent::__construct("Docker container '$containerName' not found");
    }
}
