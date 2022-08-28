<?php

declare(strict_types=1);

namespace App\Commands;

use App\Domain\Archive\ArchiveFormat;
use App\Domain\Docker\DockerVolumeArchiver;
use App\Domain\Rclone\RcloneArchiveUploader;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use function Termwind\render;

class BackupCreateCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'backup:create
                           {volume : Name of the Docker volume to archive}
                           {path? : Directory path where the archive file gets created (default: current directory)}
                           {--archive-format=zip : Archive format. Supported formats: zip}
                           {--archive-name= : Name for the archive file without extension (default: volume name)}
                           {--archive-password= : Password for the archive file}
                           {--container= : Docker container to stop and restart afterwards}
                           {--rclone-path= : Rclone path to upload the archive file to ($remote:$path) and delete local archive file afterwards}
                           {--rclone-config= : Rclone configuration file to use}
                           {--rclone-password= : Rclone configuration file password}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create an archive file of a Docker volume and optionally upload using Rclone';

    /**
     * Execute the console command.
     */
    public function handle(DockerVolumeArchiver $archiver, RcloneArchiveUploader $uploader): void
    {
        $archiveFile = $archiver
            ->setDockerContainer($this->option('container'))
            ->setDockerVolume($this->argument('volume'))
            ->setArchiveFormat(ArchiveFormat::from(Str::lower($this->option('archive-format'))))
            ->setArchiveName($this->option('archive-name') ?? $this->argument('volume'))
            ->setArchivePassword($this->option('archive-password'))
            ->setArchiveOutputPath($this->argument('path') ?? getcwd())
            ->create();

        if ($this->hasOption('rclone-path')) {
            $uploader
                ->setArchiveFile($archiveFile)
                ->setRclonePath($this->option('rclone-path'))
                ->setRcloneConfig($this->option('rclone-config'))
                ->setRclonePassword($this->option('rclone-password'))
                ->upload();

            $archiveFile->delete();
        }

        render(<<<'HTML'
            <div class="py-1 ml-2 bg-blue-400 text-black">test</div>
        HTML
        );
    }
}
