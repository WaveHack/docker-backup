<?php

declare(strict_types=1);

namespace App\Domain\Docker;

use App\Domain\Archive\ArchiveFile;
use App\Domain\Archive\ArchiveFormat;
use App\Exceptions\DockerContainerNotFoundException;
use App\Exceptions\DockerVolumeNotFoundException;
use Docker\API\Model\ContainersCreatePostBody;
use Docker\API\Model\HostConfig;

class DockerVolumeArchiver
{
    private const ARCHIVER_DOCKER_IMAGE = 'joshkeegan/zip:latest';
    private const ARCHIVER_DOCKER_CONTAINER_NAME = 'docker-backup-worker';

    private ?string $dockerContainerName;
    private ?string $dockerVolumeName;

    private ArchiveFormat $archiveFormat = ArchiveFormat::Zip;
    private string $archiveName;
    private ?string $archivePassword;
    private string $archiveOutputPath;

    public function __construct(
        private readonly DockerService $service,
    )
    {
    }

    public function setDockerContainer(?string $containerName): static
    {
        $this->dockerContainerName = $containerName;

        return $this;
    }

    public function setDockerVolume(string $volumeName): static
    {
        $this->dockerVolumeName = $volumeName;

        return $this;
    }

    public function setArchiveFormat(ArchiveFormat $format): static
    {
        $this->archiveFormat = $format;

        return $this;
    }

    public function setArchiveName(string $name): static
    {
        $this->archiveName = $name;

        return $this;
    }

    public function setArchivePassword(?string $password): static
    {
        $this->archivePassword = $password;

        return $this;
    }

    public function setArchiveOutputPath(string $path): static
    {
        $this->archiveOutputPath = $path;

        return $this;
    }

    public function create(): ArchiveFile
    {
        if (!$this->service->volumeExists($this->dockerVolumeName)) {
            throw new DockerVolumeNotFoundException($this->dockerVolumeName);
        }

        if ($this->dockerContainerName !== null) {
            if (!$this->service->containerExists($this->dockerContainerName)) {
                throw new DockerContainerNotFoundException($this->dockerContainerName);
            }

            $this->service->stopContainer($this->dockerContainerName);
        }

        $archiveFile = $this->createArchiveFile();

        if ($this->dockerContainerName !== null) {
            $this->service->startContainer($this->dockerContainerName);
        }

        return $archiveFile;
    }

    private function createArchiveFile(): ArchiveFile
    {
        if ($this->service->containerExists(static::ARCHIVER_DOCKER_CONTAINER_NAME)) {
            $this->service->deleteContainer(static::ARCHIVER_DOCKER_CONTAINER_NAME);
        }

        $archiveFullPath = $this->getArchiveFullPath();
        touch($archiveFullPath);

        $archiveShellCommand = $this->archiveFormat
            ->getArchiver()
            ->getShellCommand(
                $this->archivePassword
            );

        $containersCreatePostBody = (new ContainersCreatePostBody())
            ->setHostConfig(
                (new HostConfig())
                    ->setBinds([
                        "$this->dockerVolumeName:/backup/input:ro",
                        "{$this->getArchiveFullPath()}:/backup/output:rw",
                    ])
            )
            ->setImage(static::ARCHIVER_DOCKER_IMAGE)
            ->setCmd(['sh', '-c', "cd /backup/input; $archiveShellCommand"]);

        $this->service->createContainer(static::ARCHIVER_DOCKER_CONTAINER_NAME, $containersCreatePostBody);
        $this->service->startContainer(static::ARCHIVER_DOCKER_CONTAINER_NAME);
        $this->service->waitForContainer(static::ARCHIVER_DOCKER_CONTAINER_NAME);
        $this->service->deleteContainer(static::ARCHIVER_DOCKER_CONTAINER_NAME);

        return new ArchiveFile(
            path: $archiveFullPath,
        );
    }

    private function getArchiveFullPath(): string
    {
        $filename = sprintf(
            '%s-%s.%s',
            now()->format('Ymd-His'),
            $this->archiveName,
            $this->archiveFormat->getFileExtension(),
        );

        return ($this->archiveOutputPath . DIRECTORY_SEPARATOR . $filename);
    }
}
