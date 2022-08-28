<?php

declare(strict_types=1);

namespace App\Domain\Archive;

interface Archiver
{
    public function getShellCommand(?string $archivePassword): string;
}
