<?php

declare(strict_types=1);

namespace App\Domain\Docker;

use App\Exceptions\DockerContainerNotFoundException;
use Docker\API\Exception\VolumeInspectNotFoundException;
use Docker\API\Model\ContainersCreatePostBody;
use Docker\API\Model\ContainersCreatePostResponse201;
use Docker\API\Model\ContainerSummaryItem;
use Docker\Docker;

class DockerService
{
    private readonly Docker $docker;

    public function __construct()
    {
        $this->docker = Docker::create();
    }

    public function containerExists(string $containerName): bool
    {
        try {
            $this->getContainerByName($containerName);
            return true;

        } catch (DockerContainerNotFoundException) {
            return false;
        }
    }

    public function createContainer(string $containerName, ContainersCreatePostBody $containersCreatePostBody): ContainersCreatePostResponse201
    {
        return $this->docker->containerCreate($containersCreatePostBody, [
            'name' => $containerName,
        ]);
    }

    public function deleteContainer(string $containerName): void
    {
        $container = $this->getContainerByName($containerName);
        $this->docker->containerDelete($container->getId());
    }

    public function startContainer(string $containerName): void
    {
        $container = $this->getContainerByName($containerName);

        $this->docker->containerStart($container->getId());
    }

    public function stopContainer(string $containerName): void
    {
        $container = $this->getContainerByName($containerName);

        $this->docker->containerStop($container->getId());
    }

    public function volumeExists(string $volumeName): bool
    {
        try {
            $this->docker->volumeInspect($volumeName);
            return true;

        } catch (VolumeInspectNotFoundException) {
            return false;
        }
    }

    public function waitForContainer(string $containerName): void
    {
        $container = $this->getContainerByName($containerName);

        $this->docker->containerWait($container->getId());
    }

    private function getContainerByName(string $containerName): ContainerSummaryItem
    {
        return collect($this->docker->containerList([
            'all' => true, // also fetch non-running containers
        ]))->first(
            fn(ContainerSummaryItem $item) => collect($item->getNames())->contains("/$containerName"),
            fn() => throw new DockerContainerNotFoundException($containerName)
        );
    }
}
