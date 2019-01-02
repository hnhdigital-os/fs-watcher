<?php

namespace App\Commands;

use App\Shared\Command;
use App\Traits\ProcessesTrait;

class LogCommand extends Command
{
    use ProcessesTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'watch:log
                            {pid? : Specify a process PID to watch}
                            {--where : Show where logs are going}
                            {--clear : Clear the logs}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'View the current log for a watcher';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('where')) {
            $this->bigInfo($this->getWorkingDirectory(''));

            return;
        }

        if ($this->option('clear')) {
            $this->clearLog($this->argument('pid'));

            return;
        }

       return $this->getLog($this->argument('pid'));
    }
}
