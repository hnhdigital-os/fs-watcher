<?php

namespace App\Commands;

use App\Traits\CommonTrait;
use App\Traits\ProcessesTrait;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class LoadCommand extends Command
{
    use CommonTrait, ProcessesTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'watch:load
                            {config-file : Specify a Yaml config file to load multiple watchers (load)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Load watchers from a config file';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $config_file = $this->argument('config-file');

        if (!file_exists($config_file_path = $config_file)) {
            $config_file_path = base_path().'/'.$config_file;
            if (!file_exists($config_file_path)) {
                $this->bigError(sprintf('Config file %s can not be found.', $config_file));

                return 1;
            }
        }

        if (!is_file($config_file)) {
            $this->bigError(sprintf('Path provided %s is not a file.', $config_file));

            return 1;
        }

        try {
            $config = Yaml::parse(file_get_contents($config_file_path));
        } catch (ParseException $e) {
            $this->bigError(sprintf('Unable to parse %s %s', $config_file, $e->getMessage()));

            return 1;
        }

        if (is_string($config)) {
            $this->bigError(sprintf('Configuration file has incorrect format.', $config_file));
            $this->line('[folder path]:');
            $this->line("- [binary]: [arguments]");
            $this->line('---');
            $this->line($config);

            return 1;
        }

        foreach ($config as $folder => $scripts) {
            if (!file_exists($folder_path = $folder)) {
                $folder_path = base_path().'/'.$folder;
                if (!file_exists($config_file_path)) {
                    $this->bigError(sprintf('Folder %s requested to watch does not exist.', $folder));

                    return 1;
                }
            }

            foreach ($scripts as $script) {
                foreach ($script as $binary => $script_arguments) {
                    if (!file_exists($binary)) {
                        $this->bigError(sprintf('Binary %s does not exist.', $binary));

                        return 1;
                    }
                }
            }
        }

        foreach ($config as $folder => $scripts) {
            foreach ($scripts as $script) {
                foreach ($script as $binary => $script_arguments) {
                    $this->addLog(sprintf('Will watch \'%s\' and run \'%s %s\'', $folder_path, $binary, str_replace('%s', '<file-path>', $script_arguments)), getmypid());
                    $this->backgroundProcess($folder_path, $binary, $script_arguments);
                }
            }
        }

        return $this->listProcesses();
    }
}
