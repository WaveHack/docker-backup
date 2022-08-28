<?php

namespace App;

use App\Exceptions\DockerContainerNotFoundException;
use Docker\API\Exception\VolumeInspectNotFoundException;
use Docker\API\Model\ContainersCreatePostBody;
use Docker\API\Model\ContainersCreatePostResponse201;
use Docker\API\Model\ContainerSummaryItem;
use Docker\API\Model\HostConfig;
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

        } catch (DockerContainerNotFoundException $e) {
            return false;
        }
    }

    public function deleteContainer(string $containerName): void
    {
        $container = $this->getContainerByName($containerName);
        $this->docker->containerDelete($container->getId());
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

        } catch (VolumeInspectNotFoundException $e) {
            return false;
        }
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

    public function createZipFromVolume(string $volumeName, string $path, string $zipName, ?string $zipPassword): void
    {
        $zipFilePath = $this->getZipFilePath($zipName, $path);
        $zipCommand = $this->getZipCommand($zipPassword);

        touch($zipFilePath);

        $postBody = (new ContainersCreatePostBody())
            ->setHostConfig((new HostConfig())
                ->setBinds([
                    "{$volumeName}:/backup/input:ro",
                    "{$zipFilePath}:/backup/output:rw",
                ])
            )
            ->setImage('joshkeegan/zip:latest')
//            ->setCmd(['sh', '-c', "\"cd /backup/input; {$zipCommand}\""]) // werkt niet
//            ->setCmd(["sh -c \"cd /backup/input; {$zipCommand}\""]) // werkt niet
            ->setCmd(['sh -c "echo foo > /backup/output"']) // werkt ook niet reeeeeeeee
        ;

        $containerName = 'docker-backup';

        if ($this->containerExists($containerName)) {
            $this->deleteContainer($containerName);
        }

        /** @var ContainersCreatePostResponse201 $response */
        $response = $this->docker->containerCreate($postBody, [
            'name' => $containerName,
        ]);

        $this->docker->containerStart($response->getId());
        $this->docker->containerWait($response->getId());

        if (filesize($zipFilePath) === 0) {
            dump('written 0 bytes');
            exit(1);
        }

        $this->docker->containerDelete($response->getId());
    }

    private function getZipFilePath(string $zipName, string $path): string
    {
        $fullZipName = sprintf(
            "%s-%s.zip",
            now()->format('Ymd-His'),
            $zipName
        );

        return ($path . DIRECTORY_SEPARATOR . $fullZipName);
    }

    private function getZipCommand(?string $zipPassword): string
    {
        return collect([
            'zip',
//            '--quiet',
            '--recurse-paths',
            ($zipPassword !== null ? "--encrypt --password \"{$zipPassword}\"" : null),
            '-', // output zip contents to stdout
            '.', // input
            '> /backup/output', // pipe stdout to file
        ])->filter()->implode(' ');
    }
}
