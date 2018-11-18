<?php

namespace App\Traits;

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
        return $this->getWorkingDirectory('config.yml');
    }

    /**
     * Get the file path that we're using to store our process logs.
     *
     * @return string
     */
    private function logPath($pid = 0)
    {
        if (empty($pid)) {
            return $this->getWorkingDirectory('wp-monitor.log');
        }

        return $this->getWorkingDirectory('pid/'.$pid.'.log');
    }

    /**
     * Get the working directory to save process and logs.
     *
     * @param string $file_name
     *
     * @return string
     */
    private function getWorkingDirectory($file_name)
    {
        $path = env('XDG_RUNTIME_DIR') ? env('XDG_RUNTIME_DIR') : $this->getUserHome();
        $path = empty($path) ? $_SERVER['TMPDIR'] : $path;
        $path .= '/fs-monitor';
        $path .= '/'.$file_name;

        // Create working directory.
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        // Create empty file.
        if (!file_exists($path)) {
            file_put_contents($path, '');
        }

        return $path;
    }

}
