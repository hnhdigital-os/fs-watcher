<?php

namespace App\Traits;

use Symfony\Component\Yaml\Yaml;

trait CommonTrait
{
    /**
     * Check requirements.
     *
     * @return integer|void
     */
    private function checkRequirements()
    {
        if (!function_exists('inotify_init')) {
            $this->bigError('You need to install PECL inotify to be able to use watcher.');

            exit(1);
        }

        $this->getDefaultWorkingDirectory('');

        // Just in case it doesn't exist.
        if ($_SERVER['argv'][1] != 'config') {
            $this->getUserWorkingDirectory('');
        }
    }
    

    /**
     * Get current script.
     *
     * @return string
     */
    private function getCurrentScript()
    {
        list($script_path) = get_included_files();

        return $script_path;
    }

    /**
     * Print a big output.
     *
     * @param string $method
     * @param string $text
     *
     * @return void
     */
    private function bigPrintLine($method, $text)
    {
        $text_length = strlen($text) + 4;

        $this->line('');
        $this->$method(str_repeat(' ', $text_length));
        $this->$method('  '.$text.'  ');
        $this->$method(str_repeat(' ', $text_length));
        $this->line('');
    }

    /**
     * Output a big line.
     *
     * @param string $text
     *
     * @return void
     */
    private function bigLine($text)
    {
        $this->bigPrintLine('line', $text);
    }

    /**
     * Output a big info.
     *
     * @param string $text
     *
     * @return void
     */
    private function bigInfo($text)
    {
        $this->bigPrintLine('info', $text);
    }

    /**
     * Output a big error.
     *
     * @param string $text
     *
     * @return void
     */
    private function bigError($text)
    {
        $this->bigPrintLine('error', $text);
    }

    /**
     * Add text to the log.
     *
     * @param string $text
     * @param int    $pid
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    private function addLog($text, $pid = 0)
    {
        if ($pid === 0) {
            $pid = getmypid();
        }

        $log_path = $this->logPath($pid);
        $file_handle = fopen($log_path, 'a+');
        $log_text = sprintf('[%s] <%s> %s', date('Y-m-d H:i:s'), $pid, $text);

        fwrite($file_handle, $log_text."\n");
        fclose($file_handle);

        $this->line($log_text);
    }

    /**
     * Clear the log.
     *
     * @return void
     */
    private function clearLog()
    {
        $log_path = $this->logPath($pid);
        file_put_contents($log_path, '');

        $this->bigInfo('Logs have been cleared.');
    }

    /**
     * Delete the log.
     *
     * @return void
     */
    private function deleteLog($pid, $output = true)
    {
        $log_path = $this->logPath($pid);
        unlink($log_path);

        if ($output) {
            $this->bigInfo('Logs have been cleared.');
        }
    }

    /**
     * Log for a specific process.
     *
     * @param int|string $pid
     *
     * @return void
     */
    private function getLog($pid)
    {
        $log_path = $this->logPath($pid);

        $size = 0;

        while (true) {
            clearstatcache();
            $current_size = filesize($log_path);
            if ($size == $current_size) {
                usleep(10000);
                continue;
            }
            $file_handle = fopen($log_path, 'r');
            fseek($file_handle, $size);
            while ($line = fgets($file_handle)) {
                if ($pid === 'all' || stripos($line, '<'.$pid.'>') !== false) {
                    $this->line(trim($line));
                }
            }
            fclose($file_handle);
            $size = $current_size;
        }
    }

    /**
     * Get the config path.
     *
     * @return string
     */
    private function configPath()
    {
        return $this->getDefaultWorkingDirectory('config.yml');
    }

    /**
     * Get the file path that we're using to store our process logs.
     *
     * @return string
     */
    private function logPath($pid = 0)
    {
        if (empty($pid)) {
            return $this->getUserWorkingDirectory('logs/wp-monitor.log');
        }

        return $this->getUserWorkingDirectory('logs/pid/'.$pid.'.log');
    }

    /**
     * Get the working directory to save process and logs.
     *
     * @param string $file_name
     *
     * @return string
     */
    private function getUserWorkingDirectory($file_name)
    {
        if (empty(config('user.working-directory'))
            || config('user.working-directory') == 'default') {
            return $this->getDefaultWorkingDirectory($file_name);
        }

        if (!empty(config('user.working-directory'))) {
            $path = config('user.working-directory');

            if (!file_exists($path)) {
                $this->bigError(sprintf('Supplied working directory %s does not exist.', $path));

                exit();
            }
        }

        $path .= '/'.$file_name;

        $this->checkWorkingDirectory($path);

        return $path;
    }

    /**
     * Get default working directory.
     *
     * @return string
     */
    private function getDefaultWorkingDirectory($file_name = '')
    {
        $path = env('XDG_RUNTIME_DIR') ? env('XDG_RUNTIME_DIR') : $this->getUserHome();
        $path = empty($path) ? $_SERVER['TMPDIR'] : $path;
        $path .= '/'.config('app.directory');

        if (!empty($file_name)) {
            $path .= '/'.$file_name;
            $this->checkWorkingDirectory($path);
        } else {
            $this->checkWorkingDirectory($path, false);
        }

        return $path;
    }

    /**
     * Return the user's home directory.
     */
    private function getUserHome()
    {
        // Linux home directory
        $home = getenv('HOME');

        if (!empty($home)) {
            $home = rtrim($home, '/');
        }

        // Windows home directory
        elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
            $home = rtrim($_SERVER['HOMEDRIVE'].$_SERVER['HOMEPATH'], '\\/');
        }

        return empty($home) ? null : $home;
    }

    /**
     * Check working directory.
     *
     * @return string
     */
    private function checkWorkingDirectory($path, $check_file = true)
    {
        // Create working directory.
        if (!file_exists($dirname_path = $check_file ? dirname($path) : $path)) {
            mkdir($dirname_path, 0755, true);
        }

        // Create empty file.
        if ($check_file && !file_exists($path)) {
            file_put_contents($path, '');
        }
    }

    /**
     * Load user config.
     *
     * @return array
     */
    private function loadUserConfig()
    {
        $user_config = $this->configPath();

        try {
            $result = Yaml::parse(file_get_contents($user_config));

            return is_array($result) ? $result : [];
        } catch (ParseException $e) {
            file_put_contents($user_config, '');

            return [];
        }
    }

    /**
     * Save user config.
     *
     * @return array
     */
    private function saveUserConfig($data)
    {
        $user_config = $this->configPath();

        file_put_contents($user_config, Yaml::dump($data));
    }
}
