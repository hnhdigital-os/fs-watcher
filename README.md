# FS-WATCHER

Provides the ability to watch folders for changes and call a binary in response.

[![Latest Stable Version](https://img.shields.io/github/release/hnhdigital-os/fs-watcher.svg)](https://travis-ci.org/hnhdigital-os/fs-watcher) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT) [![Donate to this project using Patreon](https://img.shields.io/badge/patreon-donate-yellow.svg)](https://patreon.com/RoccoHoward)

This package has been developed by H&H|Digital, an Australian botique developer. Visit us at [hnh.digital](http://hnh.digital).

## Requirements

* PHP 7.1.3 (min)
* php-inotify

## Installation

`bash <(curl -s https://hnhdigital-os.github.io/fs-watcher/install)`

## Updating

This tool provides a self-update mechanism. Simply run the self-update command.

`fs-watcher self-update`

## How to use

```
USAGE: fs-watcher <command> [options] [arguments]
  config           <set|get|reset> [key] [value]
                   Manage the configuration for this utility.

  self-update      Check if there is a new version and update.
  self-update      [--tag=?]
                   Update this binary to a specific tagged release.
  self-update      [--check-release=?]
                   Returns the current binary version.
  watch:now        [watch-path] [binary-path] [--script-arguments=""]
                   Specify the path to watch, when a file change is detected
                   call the specified binary at the path with the specific script
                   arguments.
  watch:background [watch-path] [binary-path] [--script-arguments=""]
                   Runs process in the background. Specify the path to watch,
                   when a file change is detected call the specified binary
                   at the path with the specific script arguments.
  watch:load       Load watchers from a config file.
  watch:list       List all current watchers.
  watch:kill       [pid]
                   Kill a specific process ID for a current watcher.
  watch:kill all   Kills all the watchers.
  watch:log        [pid]
                   View the current log for a specific process ID.
  watch:log        [--where]
                   Returns the path of the log files.
  watch:log        [pid] [--clear]
                   Clears the logs for a specifici process ID.
  watch:log        [--clear]
                   Clears all the logs.

```

## Contributing

Please see [CONTRIBUTING](https://github.com/hnhdigital-os/fs-watcher/blob/master/CONTRIBUTING.md) for details.

## Credits

* [Rocco Howard](https://github.com/RoccoHoward)
* [All Contributors](https://github.com/hnhdigital-os/fs-watcher/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/hnhdigital-os/fs-watcher/blob/master/LICENSE.md) for more information.
