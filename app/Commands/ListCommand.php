<?php

namespace App\Commands;

use App\Traits\CommonTrait;
use App\Traits\ProcessesTrait;
use LaravelZero\Framework\Commands\Command;

class ListCommand extends Command
{
    use CommonTrait, ProcessesTrait;

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
