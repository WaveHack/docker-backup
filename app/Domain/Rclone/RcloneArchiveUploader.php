<?php

declare(strict_types=1);

namespace App\Domain\Rclone;

use App\Domain\Archive\ArchiveFile;

class RcloneArchiveUploader
{
    public ArchiveFile $archiveFile;

    public string $rclonePath;
    public ?string $rcloneConfig;
    public ?string $rclonePassword;

    public function setArchiveFile(ArchiveFile $archiveFile): static
    {
        $this->archiveFile = $archiveFile;

        return $this;
    }

    public function setRclonePath(string $path): static
    {
        $this->rclonePath = $path;

        return $this;
    }

    public function setRcloneConfig(?string $config): static
    {
        $this->rcloneConfig = $config;

        return $this;
    }

    public function setRclonePassword(?string $password): static
    {
        $this->rclonePassword = $password;

        return $this;
    }

    public function upload(): void
    {
        dump($this);
    }
}
