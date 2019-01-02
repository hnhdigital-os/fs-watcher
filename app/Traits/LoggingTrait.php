<?php

namespace App\Traits;

use Symfony\Component\Yaml\Yaml;

trait LoggingTrait
{
    /**
     * Get the file path that we're using to store our process logs.
     *
     * @return string
     */
    private function logPath($pid = 0)
    {
        if (empty($pid)) {
            return $this->getConfigPath('logs/wp-monitor.log', true);
        }

        return $this->getConfigPath('logs/pid/'.$pid.'.log', true);
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

        $log_text = sprintf('[%s] <%s> %s', date('Y-m-d H:i:s'), $pid, $text);
        $this->line($log_text);

        if (config('user.disable-logging')) {
            return;
        }

        $log_path = $this->logPath($pid);
        $file_handle = fopen($log_path, 'a+');

        fwrite($file_handle, $log_text."\n");
        fclose($file_handle);

    }

    /**
     * Clear the log.
     *
     * @return void
     */
    private function clearLog($pid)
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
        if (config('user.disable-logging')) {
            $this->bigInfo('Logging was disabled.');

            return;
        }

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
}
