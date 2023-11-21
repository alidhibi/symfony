<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

class Terminal
{
    private static int $width;

    private static int $height;

    private static bool $stty;

    /**
     * Gets the terminal width.
     *
     * @return int
     */
    public function getWidth()
    {
        $width = getenv('COLUMNS');
        if (false !== $width) {
            return (int) trim($width);
        }

        if (null === self::$width) {
            $this->initDimensions();
        }

        return self::$width ?: 80;
    }

    /**
     * Gets the terminal height.
     *
     * @return int
     */
    public function getHeight()
    {
        $height = getenv('LINES');
        if (false !== $height) {
            return (int) trim($height);
        }

        if (null === self::$height) {
            $this->initDimensions();
        }

        return self::$height ?: 50;
    }

    /**
     * @internal
     *
     */
    public static function hasSttyAvailable(): bool
    {
        return self::$stty;
    }

    private function initDimensions(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            if (preg_match('/^(\d+)x(\d+)(?: \((\d+)x(\d+)\))?$/', trim(getenv('ANSICON')), $matches)) {
                // extract [w, H] from "wxh (WxH)"
                // or [w, h] from "wxh"
                self::$width = (int) $matches[1];
                self::$height = isset($matches[4]) ? (int) $matches[4] : (int) $matches[2];
            } elseif (!$this->hasVt100Support() && self::hasSttyAvailable()) {
                // only use stty on Windows if the terminal does not support vt100 (e.g. Windows 7 + git-bash)
                // testing for stty in a Windows 10 vt100-enabled console will implicitly disable vt100 support on STDOUT
                $this->initDimensionsUsingStty();
            } elseif (null !== $dimensions = $this->getConsoleMode()) {
                // extract [w, h] from "wxh"
                self::$width = $dimensions[0];
                self::$height = $dimensions[1];
            }
        } else {
            $this->initDimensionsUsingStty();
        }
    }

    /**
     * Returns whether STDOUT has vt100 support (some Windows 10+ configurations).
     */
    private function hasVt100Support(): bool
    {
        return \function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support(fopen('php://stdout', 'w'));
    }

    /**
     * Initializes dimensions using the output of an stty columns line.
     */
    private function initDimensionsUsingStty(): void
    {
        if ($sttyString = $this->getSttyColumns()) {
            if (preg_match('/rows.(\d+);.columns.(\d+);/i', $sttyString, $matches)) {
                // extract [w, h] from "rows h; columns w;"
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            } elseif (preg_match('/;.(\d+).rows;.(\d+).columns/i', $sttyString, $matches)) {
                // extract [w, h] from "; h rows; w columns"
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            }
        }
    }

    /**
     * Runs and parses mode CON if it's available, suppressing any error output.
     *
     * @return int[]|null An array composed of the width and the height or null if it could not be parsed
     */
    private function getConsoleMode(): ?array
    {
        $info = $this->readFromProcess('mode CON');

        if (null === $info || !preg_match('/--------+\r?\n.+?(\d+)\r?\n.+?(\d+)\r?\n/', $info, $matches)) {
            return null;
        }

        return [(int) $matches[2], (int) $matches[1]];
    }

    /**
     * Runs and parses stty -a if it's available, suppressing any error output.
     *
     */
    private function getSttyColumns(): ?string
    {
        return $this->readFromProcess('stty -a | grep columns');
    }

    /**
     *
     * @return string|null
     */
    private function readFromProcess(string $command): null|string|bool
    {
        if (!\function_exists('proc_open')) {
            return null;
        }

        $descriptorspec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorspec, $pipes, null, null, ['suppress_errors' => true]);
        if (!\is_resource($process)) {
            return null;
        }

        $info = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $info;
    }
}
