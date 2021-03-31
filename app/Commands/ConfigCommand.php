<?php

namespace App\Commands;

use App\Shared\Command;
use App\Traits\ProcessesTrait;
use Illuminate\Support\Str;

class ConfigCommand extends Command
{
    use ProcessesTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'config
                            {action? : set|get|reset}
                            {key? : Key to set or get}
                            {value? : Value to set}';

    /**
     * The description of the command.
     *
     * @var string
     */
    public function getDescription()
    {
        $description = "<set|get|reset> [key] [value]\n";
        $description .= "                   Manage the configuration for this utility.";

        return $description;
    }

    /**
     * Config keys.
     *
     * @var array
     */
    private $config_keys = [
        'working-directory',
        'disable-logging',
    ];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->checkRequirements();

        switch ($this->argument('action')) {
            case 'set':
                $this->setConfig($this->argument('key'), $this->argument('value'));
                return;
            case 'reset':
                $this->setConfig($this->argument('key'), 'default');
                return;
            case 'get':
                return $this->getConfig($this->argument('key'));
        }

        $this->line('');
        
        foreach ($this->config() as $key => $value) {
            $this->line(sprintf('  %s: %s', $key, $value));
        }

        $this->line('');
    }

    /**
     * Current config.
     *
     * @return array
     */
    private function config()
    {
        $config = config('user');

        if ($config['working-directory'] == 'default') {
            $config['working-directory'] = $this->getConfigPath();
        }

        $config['user-config-file'] = $this->getConfigFilePath();
        $config['watcher-file'] = $this->getConfigPath('watcher.yml', true);

        return $config;
    }

    /**
     * Get config.
     */
    private function getConfig($key)
    {
        $user_config = $this->loadYamlFile($this->getConfigFilePath());
    }

    /**
     * Set config.
     */
    private function setConfig($key, $value)
    {
        if (empty($value) && strlen($value) == 0) {
            return;
        }

        if (!in_array($key, $this->config_keys)) {
            return;
        }

        $key_config_method = Str::camel('set_config_'.$key);

        if (method_exists($this, $key_config_method)
            && !$this->$key_config_method($value)) {
            return;
        }

        $user_config = $this->loadYamlFile($this->getConfigFilePath());
        if ($value == 'default') {
            unset($user_config[$key]);
        } else {
            $user_config[$key] = $value;
        }

        $this->saveYamlFile($this->getConfigFilePath(), $user_config);
    }

    /**
     * Check config value.
     *
     * @param string $value
     *
     * @return boolean
     */
    private function setConfigWorkingDirectory($value)
    {
        if ($value == 'default') {
            return true;
        }

        $this->bigLine($value);

        if (!file_exists($value)) {
            $answer = strtolower($this->confirm('This path does not exist. Create it?', true));

            if ($answer) {
                mkdir($value, 0755, true);

                return true;
            }

            return false;
        }

        return true;
    }
}
