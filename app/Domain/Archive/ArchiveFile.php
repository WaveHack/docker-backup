<?php

declare(strict_types=1);

namespace App\Domain\Archive;

class ArchiveFile
{
    public function __construct(
        public readonly string $path,
    )
    {
    }

    public function delete(): void
    {
        unlink($this->path);
    }
}
