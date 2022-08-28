<?php

declare(strict_types=1);

namespace App\Domain\Archive;

class ZipArchiver implements Archiver
{
    public function getShellCommand(?string $archivePassword): string
    {
        return collect([
            'zip',
            '--recurse-paths',
            ($archivePassword !== null ? "--encrypt --password \"$archivePassword\"" : null),
            '-', // output zip contents to stdout
            '.', // input path
            '> /backup/output', // pipe stdout to file
        ])->filter()->implode(' ');
    }
}
