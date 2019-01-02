<?php

namespace App\Commands;

use App\Shared\Command;
use App\Traits\WatchTrait;

class BackgroundCommand extends Command
{
    use WatchTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'watch:background
                            {watch-path : Specify a path to watch for file system changes}
                            {binary : Specify the binary that is called on a file system change}
                            {--script-arguments= : Specify the arguments to run against the binary that is called on a file system change}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Run background watcher on the supplied path';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->checkRequirements();

        return $this->backgroundProcess($directory_path, $binary, $script_arguments);
    }
}
