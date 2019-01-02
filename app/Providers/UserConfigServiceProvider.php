<?php

namespace App\Providers;

use HnhDigital\CliHelper\CommandInternalsTrait;
use HnhDigital\CliHelper\FileSystemTrait;
use Illuminate\Support\ServiceProvider;

class UserConfigServiceProvider extends ServiceProvider
{
    use CommandInternalsTrait, FileSystemTrait;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $user_config = $this->loadYamlFile($this->getConfigPath('config.yml', true));
        $config = config('user');

        foreach ($user_config as $key => $value) {
            config(['user.'.$key => $value]);
        }
    }
}
