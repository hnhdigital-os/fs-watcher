<?php

namespace App\Commands;

use App\Traits\CommonTrait;
use App\Traits\ProcessesTrait;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class LogCommand extends Command
{
    use CommonTrait, ProcessesTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'watch:log
                            {pid : Specify a process PID to watch}';

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
       return $this->logForProcess($this->argument('pid'));
    }
}
