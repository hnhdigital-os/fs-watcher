<?php

namespace App\Shared;

use App\Traits\LoggingTrait;
use HnhDigital\CliHelper\CommandInternalsTrait;
use HnhDigital\CliHelper\OutputTrait;
use LaravelZero\Framework\Commands\Command as ZeroCommand;

class Command extends ZeroCommand
{
    use OutputTrait;
    use LoggingTrait;
    use CommandInternalsTrait;

    /**
     * Check requirements.
     *
     * @return integer|void
     */
    protected function checkRequirements()
    {
        if (!function_exists('inotify_init')) {
            $this->bigError('You need to install PECL inotify to be able to use watcher.');

            exit(1);
        }

        $this->getConfigPath();
    }
    

    /**
     * Get current script.
     *
     * @return string
     */
    protected function getCurrentScript()
    {
        list($script_path) = get_included_files();

        return $script_path;
    }

    /**
     * Get the config path.
     *
     * @return string
     */
    protected function getConfigFilePath()
    {
        return $this->getConfigPath('config.yml', true);
    }
}
