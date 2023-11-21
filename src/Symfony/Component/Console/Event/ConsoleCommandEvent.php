<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Event;

/**
 * Allows to do things before the command is executed, like skipping the command or changing the input.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConsoleCommandEvent extends ConsoleEvent
{
    /**
     * The return code for skipped commands, this will also be passed into the terminate event.
     */
    final const RETURN_CODE_DISABLED = 113;

    /**
     * Indicates if the command should be run or skipped.
     */
    private bool $commandShouldRun = true;

    /**
     * Disables the command, so it won't be run.
     *
     */
    public function disableCommand(): bool
    {
        return $this->commandShouldRun = false;
    }

    /**
     * Enables the command.
     *
     */
    public function enableCommand(): bool
    {
        return $this->commandShouldRun = true;
    }

    /**
     * Returns true if the command is runnable, false otherwise.
     *
     */
    public function commandShouldRun(): bool
    {
        return $this->commandShouldRun;
    }
}
