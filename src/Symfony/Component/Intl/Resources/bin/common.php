<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

define('LINE_WIDTH', 75);

define('LINE', str_repeat('-', LINE_WIDTH)."\n");

function bailout($message): never
{
    echo wordwrap($message, LINE_WIDTH)." Aborting.\n";

    exit(1);
}

function strip_minor_versions($version)
{
    preg_match('/^(?P<version>\d\.\d|\d{2,})/', $version, $matches);

    return $matches['version'];
}

function centered(string $text): string
{
    $padding = (int) ((LINE_WIDTH - strlen($text)) / 2);

    return str_repeat(' ', $padding).$text;
}

function cd($dir): void
{
    if (false === chdir($dir)) {
        bailout(sprintf('Could not switch to directory %s.', $dir));
    }
}

function run(string $command): void
{
    exec($command, $output, $status);

    if (0 !== $status) {
        $output = implode("\n", $output);
        echo "Error while running:\n    ".getcwd().'$ '.$command."\nOutput:\n".LINE.($output . PHP_EOL).LINE;

        bailout(sprintf('"%s" failed.', $command));
    }
}

function get_icu_version_from_genrb(string $genrb)
{
    exec($genrb.' --version - 2>&1', $output, $status);

    if (0 !== $status) {
        bailout($genrb.' failed.');
    }

    if (!preg_match('/ICU version ([\d\.]+)/', implode('', $output), $matches)) {
        return null;
    }

    return $matches[1];
}

error_reporting(\E_ALL);

set_error_handler(static function ($type, $msg, $file, $line) : never {
    throw new \ErrorException($msg, 0, $type, $file, $line);
});

set_exception_handler(static function (Throwable $exception) : void {
    echo "\n";
    $cause = $exception;
    $root = true;
    while ($cause instanceof \Throwable) {
        if (!$root) {
            echo "Caused by\n";
        }

        echo get_class($cause).': '.$cause->getMessage()."\n";
        echo "\n";
        echo $cause->getFile().':'.$cause->getLine()."\n";
        echo $cause->getTraceAsString()."\n";

        $cause = $cause->getPrevious();
        $root = false;
    }
});
