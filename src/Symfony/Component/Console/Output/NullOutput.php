<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * NullOutput suppresses all output.
 *
 *     $output = new NullOutput();
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class NullOutput implements OutputInterface
{
    /**
     * {@inheritdoc}
     */
    public function setFormatter(OutputFormatterInterface $formatter): void
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function getFormatter(): \Symfony\Component\Console\Formatter\OutputFormatterInterface
    {
        // to comply with the interface we must return a OutputFormatterInterface
        return new OutputFormatter();
    }

    /**
     * {@inheritdoc}
     */
    public function setDecorated($decorated): void
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function isDecorated(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setVerbosity($level): void
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function getVerbosity(): int
    {
        return self::VERBOSITY_QUIET;
    }

    /**
     * {@inheritdoc}
     */
    public function isQuiet(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isVerbose(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isVeryVerbose(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDebug(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function writeln($messages, $options = self::OUTPUT_NORMAL): void
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function write($messages, $newline = false, $options = self::OUTPUT_NORMAL): void
    {
        // do nothing
    }
}
