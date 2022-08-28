<?php

namespace App;

use App\Exceptions\DockerContainerNotFoundException;
use Docker\API\Exception\ContainerInspectNotFoundException;
use Docker\API\Exception\VolumeInspectNotFoundException;
use Docker\API\Model\ContainerConfigExposedPortsItem;
use Docker\API\Model\ContainerConfigVolumesItem;
use Docker\API\Model\ContainersCreatePostBody;
use Docker\API\Model\ContainersCreatePostResponse201;
use Docker\API\Model\ContainerSummaryItem;
use Docker\API\Model\HostConfig;
use Docker\Docker;
use Docker\Stream\DockerRawStream;
use Illuminate\Support\Str;

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

    public function createZipFromVolume(string $volumeName, string $path, string $zipName, ?string $zipPassword)
    {
        $zipFilePath = $this->getZipFilePath($zipName, $path);
        $zipCommand = $this->getZipCommand($zipPassword);

        $postBody = (new ContainersCreatePostBody())
            ->setHostConfig((new HostConfig())
//                ->setVolumeDriver('local')
                ->setBinds([
                    "{$volumeName}:/backup/input:ro",
                    "{$zipFilePath}:/backup/output:rw",
                ])
            )
            ->setImage('joshkeegan/zip:latest')
//            ->setCmd(['sh', '-c', "\"cd /backup/input; {$zipCommand}\""]) // werkt niet
//            ->setCmd(["sh -c \"cd /backup/input; {$zipCommand}\""]) // werkt niet
                ->setCmd(['sh -c "echo foo > /backup/output"']) // werkt ook niet reeeeeeeee
//            ->setAttachStdout(true)
//            ->setAttachStderr(true)
        ;

        $containerName = 'docker-backup';

        if ($this->containerExists($containerName)) {
            $this->deleteContainer($containerName);
        }

        /** @var ContainersCreatePostResponse201 $response */
        $response = $this->docker->containerCreate($postBody, [
            'name' => $containerName,
        ]);

//        $attachStream = $this->docker->containerAttach($response->getId(), [
////            'stream' => true,
//            'stdout' => true,
//            'stderr' => true,
//        ]);

        $this->docker->containerStart($response->getId());

//        $attachStream->onStdout(function ($stdout) {
//            echo $stdout;
//        });
//        $attachStream->onStderr(function ($stderr) {
//            echo $stderr;
//        });

//        $attachStream->wait();

        $this->docker->containerWait($response->getId());

        if (filesize($zipFilePath) === 0) {
            dump('written 0 bytes');
            exit(1);
        }

//        file_put_contents(
//            $path . DIRECTORY_SEPARATOR . $fullZipName,
//            file_get_contents($tmpFilePath)
//        );
//        unlink($tmpFilePath);

        $this->docker->containerDelete($response->getId());

        dd('foo');

        //
    }

    private function getZipFilePath(string $zipName, string $path): string
    {
        $fullZipName = sprintf(
            "%s-%s.zip",
            now()->format('Ymd-His'),
            $zipName
        );

        $zipFilePath = ($path . DIRECTORY_SEPARATOR . $fullZipName);

        touch($zipFilePath);

        return $zipFilePath;
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
