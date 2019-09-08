<?php

namespace App\Traits;

trait WatchTrait
{
    /**
     * IN_ACCESS = 1
     * IN_MODIFY = 2
     * IN_ATTRIB = 4
     * IN_CLOSE_WRITE = 8
     * IN_CLOSE_NOWRITE = 16
     * IN_OPEN = 32
     * IN_MOVED_FROM = 64
     * IN_MOVED_TO = 128
     * IN_CREATE = 256
     * IN_DELETE = 512
     * IN_DELETE_SELF = 1024
     * IN_MOVE_SELF = 2048
     * IN_UNMOUNT = 8192
     * IN_Q_OVERFLOW = 16384
     * IN_IGNORED = 32768
     * IN_CLOSE = 24
     * IN_MOVE = 192
     * IN_ALL_EVENTS = 4095
     * IN_ONLYDIR = 16777216
     * IN_DONT_FOLLOW = 33554432
     * IN_MASK_ADD = 536870912
     * IN_ISDIR = 1073741824
     * IN_ONESHOT = 2147483648
     */

    /**
     * Constants for what we need to be notified about.
     *
     * @var array
     */
    private $watch_constants = IN_CLOSE_WRITE | IN_MOVE | IN_CREATE | IN_DELETE;

    /**
     * Track notification watch to path.
     *
     * @var array
     */
    private $track_watches = [];

    /**
     * Options for paths.
     *
     * @var array
     */
    private $path_options = [];

    /**
     * Listen for notification.
     *
     * @return void
     */
    protected function listenForEvents()
    {
        // As long as we have watches that exist, we keep looping.
        while (count($this->track_watches)) {
            // Check the inotify instance for any change events.
            $events = inotify_read($this->watcher);

            // One or many events occured.
            if ($events !== false && count($events)) {
                foreach ($events as $event_detail) {
                    $this->processEvent($event_detail);
                }
            }
        }
    }
    /**
     * Process the events that have occured.
     *
     * @param array $event_detail
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function processEvent($event_detail)
    {
        $is_dir = false;

        // Directory events have a different hex, convert to the same number for a file event.
        $event_id = $event_detail['mask'];
        $dechex = (string) dechex($event_id);

        // Correctly apply for 40.
        if ($dechex === '40') {
            $event_id = IN_MOVED_FROM;
        } elseif (substr($dechex, 0, 1) === '4') {
            $dechex[0] = '0';
            $event_id = hexdec((int) $dechex);
            $is_dir = true;
        }

        // This event is ignored, obviously.
        if ($event_detail['mask'] == IN_IGNORED) {
            return;
        }

        // This event refers to a path that exists.
        elseif (isset($this->track_watches[$event_detail['wd']])) {
            // File or folder path
            $file_path = $this->track_watches[$event_detail['wd']].'/'.$event_detail['name'];
            $path_options = $this->path_options[$event_detail['wd']];
            $this->addLog(sprintf('%s event: [%s] %s', $is_dir ? 'Folder' : 'File', $event_id, $file_path));
            if ($is_dir) {
                switch ($event_id) {
                    // New folder created.
                    case IN_CREATE:
                    // New folder was moved, so need to watch new folders.
                    // New files will run the command.
                    case IN_MOVED_TO:
                        $this->addWatchPath($file_path, $path_options);
                        break;
                    // Folder was deleted or moved.
                    // Each file will trigger and event and so will run the command then.
                    case IN_DELETE:
                    case IN_MOVED_FROM:
                        $this->removeWatchPath($file_path);
                        break;
                }
                return;
            }

            // Check file extension against the specified filter.
            $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);

            if (isset($path_options['filter']) && $file_extension != '') {
                if (count($path_options['filter_allowed']) && !in_array($file_extension, $path_options['filter_allowed'])) {
                    return;
                }
                if (count($path_options['filter_not_allowed']) && in_array('!'.$file_extension, $path_options['filter_not_allowed'])) {
                    return;
                }
            }

            // Run the specified command.
            $this->runCommand($file_path, $event_id);
        }
    }

    /**
     * Run the given provided command.
     *
     * @param string $file_path
     * @param string $event_id
     *
     * @return void
     */
    protected function runCommand($file_path, $event_id)
    {
        $find_replace = [
            'event-id'  => $event_id,
            'file-path' => '"'.$file_path.'"',
            'root-path' => '"'.$this->root_path.'"',
        ];

        $find_values = array_map(static function ($key) {
            return '{{'.$key.'}}';
        }, array_keys($find_replace));

        $replace_values = array_values($find_replace);

        $command = str_replace($find_values, $replace_values, $this->command);

        $this->addLog('Running: '.$command);

        exec($command);
    }

    /**
     * Add a path to watch.
     *
     * @param string     $path
     * @param bool|array $options
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    protected function addWatchPath($original_path, $options = false)
    {
        $path = trim($original_path);

        if ($options === false) {
            list($path, $options) = self::parseOptions($path);
        }

        if (isset($options['filter'])) {
            $options['filter'] = explode(',', $options['filter']);

            $options['filter_allowed'] = array_filter($options['filter'], static function ($value) {
                return substr($value, 0, 1) !== '!';
            });

            $options['filter_not_allowed'] = array_filter($options['filter'], static function ($value) {
                return substr($value, 0, 1) === '!';
            });
        }

        // Watch this folder.
        $watch_id = inotify_add_watch($this->watcher, $path, $this->watch_constants);
        $this->track_watches[$watch_id] = $path;
        $this->path_options[$watch_id] = $options;

        if (is_dir($path)) {
            $this->addLog('Watching: '.$path);

            // Find and watch any children folders.
            $folders = $this->scan($path, true, false);

            foreach ($folders as $folder_path) {
                if (!file_exists($path)) {
                    continue;
                }

                $this->addLog('Watching: '.$folder_path);

                $watch_id = inotify_add_watch($this->watcher, $folder_path, $this->watch_constants);
                $this->track_watches[$watch_id] = $folder_path;
                $this->path_options[$watch_id] = $options;
            }
        }
    }

    /**
     * Parse options off a string.
     *
     * @return array
     */
    public static function parseOptions($input)
    {
        $input_array = explode('?', $input);

        $string = $input_array[0];

        $string_options = !empty($input_array[1]) ? $input_array[1] : '';

        $options = [];

        parse_str($string_options, $options);

        return [$string, $options];
    }

    /**
     * Scan recursively through each folder for all files and folders.
     *
     * @param string $scan_path
     * @param bool   $include_folders
     * @param bool   $include_files
     * @param int    $depth
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public static function scan($scan_path, $include_folders = true, $include_files = true, $depth = -1)
    {
        $paths = [];

        if (substr($scan_path, -1) != '/') {
            $scan_path .= '/';
        }

        $contents = scandir($scan_path);

        foreach ($contents as $value) {
            if ($value === '.' || $value === '..') {
                continue;
            }

            $absolute_path = $scan_path.$value;

            if (is_dir($absolute_path) && $depth != 0) {
                $new_paths = self::scan($absolute_path.'/', $include_folders, $include_files, $depth - 1);
                $paths = array_merge($paths, $new_paths);
            }

            if ((is_file($absolute_path) && $include_files) || (is_dir($absolute_path) && $include_folders)) {
                $paths[] = $absolute_path;
            }
        }

        return $paths;
    }

    /**
     * Remove path from watching.
     *
     * @param string $file_path
     *
     * @return void
     */
    protected function removeWatchPath($file_path)
    {
        // Find the watch ID for this path.
        $watch_id = array_search($file_path, $this->track_watches);

        // Remove the watch for this folder and remove from our tracking array.
        if ($watch_id !== false && isset($this->track_watches[$watch_id])) {
            $this->addLog('Unwatching: '.$file_path);
            try {
                inotify_rm_watch($this->watcher, $watch_id);
            } catch (\Exception $exception) {
            }

            unset($this->track_watches[$watch_id]);
            unset($this->path_options[$watch_id]);
        }
    }
}
