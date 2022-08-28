<?php

declare(strict_types=1);

namespace App\Domain\Archive;

enum ArchiveFormat: string
{
    case Zip = 'zip';

    public function getArchiver(): Archiver
    {
        return match($this) {
            self::Zip => new ZipArchiver(),
        };
    }

    public function getFileExtension(): string
    {
        return match($this) {
            self::Zip => 'zip',
        };
    }
}
