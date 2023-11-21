<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

require \dirname(__DIR__).'/vendor/autoload.php';

list('e' => $php) = getopt('e:') + ['e' => 'php'];

try {
    $process = new Process(sprintf('exec %s -r "echo \'ready\'; trigger_error(\'error\', E_USER_ERROR);"', $php));
    $process->start();
    $process->setTimeout(0.5);
    while (false === strpos($process->getOutput(), 'ready')) {
        usleep(1000);
    }

    $process->signal(\SIGSTOP);
    $process->wait();

    return $process->getExitCode();
} catch (ProcessTimedOutException $processTimedOutException) {
    echo $processTimedOutException->getMessage().\PHP_EOL;

    return 1;
}
