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

        $log_path = $this->logPath();
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
        $log_path = $this->logPath();
        file_put_contents($log_path, '');

        $this->line('');
        $this->info('Log file has been cleared.');
        $this->line('');
    }

    /**
     * Get the file path that we're using to store our process logs.
     *
     * @return string
     */
    private function logPath()
    {
        return $this->getWorkingDirectory('.log_folder_watcher.yml');
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
        $path .= '/'.$file_name;
        // Create empty file.
        if (!file_exists($path)) {
            file_put_contents($path, '');
        }
        return $path;
    }

}
