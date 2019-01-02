<?php

namespace App\Commands;

use App\Shared\Command;
use App\Traits\WatchTrait;

class WatchCommand extends Command
{
    use WatchTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'watch:now
                            {watch-path : Specify a path to watch for file system changes}
                            {binary : Specify the binary that is called on a file system change}
                            {--script-arguments= : Specify the arguments to run against the binary that is called on a file system change}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Run a watcher on the supplied path';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->checkRequirements();

        $watch_path = $this->argument('watch-path');
        $binary = $this->argument('binary');
        $script_arguments = $this->option('script-arguments');

        if (!file_exists($binary)) {
            $this->addLog(sprintf('Binary %s does not exist.', $binary));

            return 1;
        }

        $this->command = $binary.' '.$script_arguments;

        // Initialize an inotify instance.
        $this->watcher = inotify_init();
        $this->root_path = $watch_path;

        // Add the given path.
        $this->addWatchPath($watch_path);

        // Listen for notifications.
        return $this->listenForEvents();
    }
}
