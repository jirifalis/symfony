<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

/**
 * Generic executable finder.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ExecutableFinder
{
    private array $suffixes = ['.exe', '.bat', '.cmd', '.com'];
    private const CMD_BUILTINS = [
        'assoc', 'break', 'call', 'cd', 'chdir', 'cls', 'color', 'copy', 'date',
        'del', 'dir', 'echo', 'endlocal', 'erase', 'exit', 'for', 'ftype', 'goto',
        'help', 'if', 'label', 'md', 'mkdir', 'mklink', 'move', 'path', 'pause',
        'popd', 'prompt', 'pushd', 'rd', 'rem', 'ren', 'rename', 'rmdir', 'set',
        'setlocal', 'shift', 'start', 'time', 'title', 'type', 'ver', 'vol',
    ];

    /**
     * Replaces default suffixes of executable.
     */
    public function setSuffixes(array $suffixes): void
    {
        $this->suffixes = $suffixes;
    }

    /**
     * Adds new possible suffix to check for executable.
     */
    public function addSuffix(string $suffix): void
    {
        $this->suffixes[] = $suffix;
    }

    /**
     * Finds an executable by name.
     *
     * @param string      $name      The executable name (without the extension)
     * @param string|null $default   The default to return if no executable is found
     * @param array       $extraDirs Additional dirs to check into
     */
    public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
    {
        // windows built-in commands that are present in cmd.exe should not be resolved using PATH as they do not exist as exes
        if ('\\' === \DIRECTORY_SEPARATOR && \in_array(strtolower($name), self::CMD_BUILTINS, true)) {
            return $name;
        }

        $dirs = array_merge(
            explode(\PATH_SEPARATOR, getenv('PATH') ?: getenv('Path')),
            $extraDirs
        );

        $suffixes = [''];
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $pathExt = getenv('PATHEXT');
            $suffixes = array_merge($pathExt ? explode(\PATH_SEPARATOR, $pathExt) : $this->suffixes, $suffixes);
        }
        foreach ($suffixes as $suffix) {
            foreach ($dirs as $dir) {
                if ('' === $dir) {
                    $dir = '.';
                }
                if (@is_file($file = $dir.\DIRECTORY_SEPARATOR.$name.$suffix) && ('\\' === \DIRECTORY_SEPARATOR || @is_executable($file))) {
                    return $file;
                }

                if (!@is_dir($dir) && basename($dir) === $name.$suffix && @is_executable($dir)) {
                    return $dir;
                }
            }
        }

        if (!\function_exists('exec') || \strlen($name) !== strcspn($name, '/'.\DIRECTORY_SEPARATOR)) {
            return $default;
        }

        $command = '\\' === \DIRECTORY_SEPARATOR ? 'where %s 2> NUL' : 'command -v -- %s';
        $execResult = exec(\sprintf($command, escapeshellarg($name)));

        if (($executablePath = substr($execResult, 0, strpos($execResult, \PHP_EOL) ?: null)) && @is_executable($executablePath)) {
            return $executablePath;
        }

        return $default;
    }
}
