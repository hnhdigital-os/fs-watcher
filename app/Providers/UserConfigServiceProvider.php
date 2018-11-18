<?php

namespace App\Providers;

use App\Traits\CommonTrait;
use Illuminate\Support\ServiceProvider;

class UserConfigServiceProvider extends ServiceProvider
{
    use CommonTrait;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $user_config = $this->loadUserConfig();
        $config = config('user');

        foreach ($user_config as $key => $value) {
            config(['user.'.$key => $value]);
        }
    }
}
