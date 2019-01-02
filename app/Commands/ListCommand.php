<?php

namespace App\Commands;

use App\Shared\Command;
use App\Traits\ProcessesTrait;

class ListCommand extends Command
{
    use  ProcessesTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'watch:list';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'List all current watchers';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->listProcesses();
    }
}
