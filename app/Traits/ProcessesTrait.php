<?php

namespace App\Traits;

use Symfony\Component\Yaml\Yaml;

trait ProcessesTrait
{
    /**
     * Get the file path that we're using to store our background processes.
     *
     * @return string
     */
    protected function processListPath()
    {
        return $this->getConfigPath('watchers.yml', true);
    }

    /**
     * Get command hash.
     *
     * @param splat $strings
     *
     * @return string
     */
    protected function getCommandHash(...$strings)
    {
        return hash('sha256', implode(' ', $strings));
    }

    /**
     * Run the process in the background.
     *
     * @param string $directory_path
     * @param string $command
     *
     * @return int
     */
    protected function backgroundProcess($directory_path, $binary, $script_arguments)
    {
        $this->cleanProcessList();

        $data = $this->getProcessList();        

        if (!file_exists($binary)) {
            $this->addLog(sprintf('Binary %s does not exist.', $binary));

            return 1;
        }

        // Default arguments.
        if (empty($script_arguments)) {
            $script_arguments = '{{root-path}} {{file-path}} {{event-id}}';
        }

        $command_hash = $this->getCommandHash($directory_path, $binary, $script_arguments);

        if (!isset($data[$command_hash])) {
            $process_output = [];
            exec($complete_command = sprintf(
                    'nohup %s watch:now "%s" "%s" --script-arguments="%s" > /dev/null 2>&1 & echo $!',
                    $this->getCurrentScript(),
                    $directory_path,
                    $binary,
                    $script_arguments,
                    $command_hash
                ),
                $process_output
            );

            $pid = (int) $process_output[0];

            $this->addLog($complete_command, $pid);

            if ($pid > 0) {
                $this->addProcess($pid, $directory_path, $binary, $script_arguments);

                return 0;
            }

            $this->bigError('Failed to run this background process.');

            return 0;
        }

        $this->bigError('Watch already exists.');

        return 1;
    }

    /**
     * Watch the provided folder and run the given command on files.
     *
     * @param string $directory_path
     * @param string $command
     *
     * @return int
     */
    protected function runProcess($directory_path, $binary, $script_arguments)
    {
        if (!function_exists('inotify_init')) {
            $this->bigError('You need to install PECL inotify to be able to use watcher.');
            return 1;
        }

        $this->command = $binary.' '.$script_arguments;

        // Initialize an inotify instance.
        $this->watcher = inotify_init();
        $this->root_path = $directory_path;

        // Add the given path.
        $this->addWatchPath($directory_path);

        // Listen for notifications.
        return $this->listenForEvents();
    }

    /**
     * List the processes that are running in the background.
     *
     * @return void
     */
    protected function listProcesses()
    {
        $this->cleanProcessList();
        $data = $this->getProcessList();

        if (count($data)) {

            $this->line('');

            $directories = array_column($data, 'directory_path');

            foreach ($directories as $directory) {

                $this->table([$directory], []);

                $rows = [];

                foreach ($data as $pid => $process) {
                    if (is_int($process) || $process['directory_path'] != $directory) {
                        continue;
                    }

                    if (is_int($pid)) {
                        $rows[] = [
                            $pid,
                            $process['binary'].' '.str_replace('%s', '[file-path]', $process['script_arguments']),
                        ];
                    }
                }

                $this->table([], $rows);
            }

            $this->line('');
            $this->line('You can view a processes log by running:');
            $this->line('');
            $this->line('   \'watch:log [<pid>]\'');
            $this->line('');
            $this->line('You can kill a specific or all processes by running the following:');
            $this->line('');
            $this->line('   \'watch:kill [<pid>|all]\'');
            $this->line('');

            return;
        }

        $this->bigError('No active watchers.');
    }

    /**
     * Add a background process to the file.
     *
     * @param int    $pid
     * @param string $directory_path
     * @param string $command
     *
     * @return void
     */
    protected function addProcess($pid, $directory_path, $binary, $script_arguments)
    {
        $data = $this->getProcessList();

        $command_hash = $this->getCommandHash($directory_path, $binary, $script_arguments);

        $data[$pid] = [
            'directory_path'   => $directory_path,
            'binary'           => $binary,
            'script_arguments' => $script_arguments,
            'command_hash'     => $command_hash,
        ];

        $data[$command_hash] = $pid;

        $this->saveProcessList($data);
    }

    /**
     * Remove any background processes that may have terminated.
     *
     * @return void
     */
    protected function cleanProcessList()
    {
        $data = $this->getProcessList();
        $sha_to_pid = [];
        foreach ($data as $pid => $process) {
            if (is_int($pid)) {
                if (!posix_kill($pid, 0)) {
                    unset($data[$pid]);
                    $this->addLog('Process was dead', $pid);
                }
                continue;
            }
            $sha_to_pid[$process] = $pid;
        }
        foreach ($sha_to_pid as $pid => $sha) {
            if (!isset($data[$pid])) {
                unset($data[$sha]);
            }
        }
        $this->saveProcessList($data);
    }

    /**
     * Get the list of processes from the file.
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    protected function getProcessList()
    {
        $process_list_path = $this->processListPath();

        // Parse the YAML config file.
        try {
            $result = Yaml::parse(file_get_contents($process_list_path));
            return is_array($result) ? $result : [];
        } catch (ParseException $e) {
            $this->error(sprintf('Unable to parse %s %s', $process_list_path, $e->getMessage()));
            exit(1);
        }
    }

    /**
     * Save the process to the file.
     *
     * @param array $data
     *
     * @return void
     */
    protected function saveProcessList($data)
    {
        $process_list_path = $this->processListPath();
        file_put_contents($process_list_path, Yaml::dump($data));
    }

    /**
     * Kill a background process.
     *
     * @param int $pid
     *
     * @return int
     */
    protected function killProcess($pid, $output = true)
    {
        if (empty($pid)) {
            return 1;
        }

        $data = $this->getProcessList();

        if ($pid === 'all') {
            foreach (array_keys($data) as $pid) {
                if (is_int($pid)) {
                    $this->killProcess($pid, false);
                }
            }

            if ($output) {
                $this->bigInfo('Processes have been all killed.');
            }

            return 0;
        }

        $pid = (int) $pid;

        if (isset($data[$pid])) {
            unset($data[$pid]);
            $this->saveProcessList($data);
            posix_kill((int) $pid, SIGKILL);

            if ($output) {
                $this->bigInfo('Process has been killed.');
            }

            $this->deleteLog($pid, $output);

            return 0;
        }

        $this->bigError('Supplied PID did not match.');
    }
}
