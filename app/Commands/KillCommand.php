<?php

namespace App\Commands;

use App\Shared\Command;
use App\Traits\ProcessesTrait;

class KillCommand extends Command
{
    use ProcessesTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'watch:kill
                            {pid : Specify a process PID so we can kill it}';

    /**
     * The description of the command.
     *
     * @var string
     */
    public function getDescription()
    {
        $description = "[pid]\n";
        $description .= "                   Kill a specific process ID for a current watcher.\n";
        $description .= "  <info>watch:kill all</info>   Kills all the watchers.";

        return $description;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        return $this->killProcess($this->argument('pid'));
    }
}
