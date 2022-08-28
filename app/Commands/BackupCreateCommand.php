<?php

namespace App\Commands;

use App\DockerService;
use App\Exceptions\DockerContainerNotFoundException;
use App\Exceptions\DockerVolumeNotFoundException;
use App\RcloneService;
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
                           {volume : Name of the Docker volume to backup}
                           {path? : Directory path where the zip file gets created (default: current directory)}
                           {--container= : Docker container to stop and restart afterwards}
                           {--rclone= : Rclone path to upload the zip file to ($remote:$path); deletes local zip file afterwards}
                           {--rclone-config= : Rclone configuration file to use}
                           {--rclone-password= : Rclone configuration file password}
                           {--zip-name= : Name for the zip file (default: volume name)}
                           {--zip-password= : Password for the backup zip file}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a backup of a Docker volume';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(DockerService $dockerService, RcloneService $rcloneService)
    {
        $volume = $this->argument('volume');
        $path = $this->argument('path') ?? getcwd();

        $container = $this->option('container');
        $rclone = $this->option('rclone');
        $rcloneConfig = $this->option('rclone-config');
        $rclonePassword = $this->option('rclone-password');
        $zipName = $this->option('zip-name') ?? $volume;
        $zipPassword = $this->option('zip-password');

        if (!$dockerService->volumeExists($volume)) {
            throw new DockerVolumeNotFoundException($volume);
        }

        if (!$dockerService->containerExists($container)) {
            throw new DockerContainerNotFoundException($container);
        }

        $dockerService->stopContainer($container);

        $zipFile = $dockerService->createZipFromVolume($volume, $path, $zipName, $zipPassword);
        // nyi from this point onwards
        $rcloneService->upload($zipFile, $path);
        $zipFile->remove();

        $dockerService->startContainer($container);

        /*
         * docker run \
  --rm \
  --volume=$VOLUME:/backup/input:ro \
  --volume=$BASEPATH/tmp:/backup/output:rw \
  joshkeegan/zip:latest \
    sh -c "cd /backup/input; zip --encrypt --quiet --recurse-paths --password $BACKUP_ZIP_PASSWORD "/backup/output/$ZIP_FILE" ."
         */

        /*
         * rclone --config $BASEPATH/rclone/rclone.conf \
  --ask-password=false \
  copy $BASEPATH/tmp/$FILE \
  $RCLONE_REMOTE_NAME:/Backups/Intranet/$CONTAINER
         */


        render(<<<'HTML'
            <div class="py-1 ml-2 bg-blue-400 text-black">foo</div>
        HTML
        );
    }
}
