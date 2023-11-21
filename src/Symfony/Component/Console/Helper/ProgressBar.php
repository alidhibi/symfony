<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * The ProgressBar provides helpers to display progress output.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Chris Jones <leeked@gmail.com>
 */
final class ProgressBar
{
    private $barWidth = 28;

    private ?string $barChar = null;

    private string $emptyBarChar = '-';

    private string $progressChar = '>';

    private ?string $format = null;

    private string $internalFormat;

    private $redrawFreq = 1;

    private readonly \Symfony\Component\Console\Output\OutputInterface $output;

    private int $step = 0;

    private ?int $max;

    private int $startTime;

    private int $stepWidth;

    private float|int $percent = 0.0;

    private $formatLineCount;

    private array $messages = [];

    private bool $overwrite = true;

    private readonly \Symfony\Component\Console\Terminal $terminal;

    private bool $firstRun = true;

    private static ?array $formatters = null;

    private static ?array $formats = null;

    /**
     * @param OutputInterface $output An OutputInterface instance
     * @param int             $max    Maximum steps (0 if unknown)
     */
    public function __construct(OutputInterface $output, int $max = 0)
    {
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        $this->output = $output;
        $this->setMaxSteps($max);
        $this->terminal = new Terminal();

        if (!$this->output->isDecorated()) {
            // disable overwrite when output does not support ANSI codes.
            $this->overwrite = false;

            // set a reasonable redraw frequency so output isn't flooded
            $this->setRedrawFrequency($max / 10);
        }

        $this->startTime = time();
    }

    /**
     * Sets a placeholder formatter for a given name.
     *
     * This method also allow you to override an existing placeholder.
     *
     * @param string   $name     The placeholder name (including the delimiter char like %)
     * @param callable $callable A PHP callable
     */
    public static function setPlaceholderFormatterDefinition($name, callable $callable): void
    {
        if (!self::$formatters) {
            self::$formatters = self::initPlaceholderFormatters();
        }

        self::$formatters[$name] = $callable;
    }

    /**
     * Gets the placeholder formatter for a given name.
     *
     * @param string $name The placeholder name (including the delimiter char like %)
     *
     * @return callable|null A PHP callable
     */
    public static function getPlaceholderFormatterDefinition($name)
    {
        if (!self::$formatters) {
            self::$formatters = self::initPlaceholderFormatters();
        }

        return isset(self::$formatters[$name]) ? self::$formatters[$name] : null;
    }

    /**
     * Sets a format for a given name.
     *
     * This method also allow you to override an existing format.
     *
     * @param string $name   The format name
     * @param string $format A format string
     */
    public static function setFormatDefinition($name, $format): void
    {
        if (!self::$formats) {
            self::$formats = self::initFormats();
        }

        self::$formats[$name] = $format;
    }

    /**
     * Gets the format for a given name.
     *
     * @param string $name The format name
     *
     * @return string|null A format string
     */
    public static function getFormatDefinition($name)
    {
        if (!self::$formats) {
            self::$formats = self::initFormats();
        }

        return isset(self::$formats[$name]) ? self::$formats[$name] : null;
    }

    /**
     * Associates a text with a named placeholder.
     *
     * The text is displayed when the progress bar is rendered but only
     * when the corresponding placeholder is part of the custom format line
     * (by wrapping the name with %).
     *
     * @param string $message The text to associate with the placeholder
     * @param string $name    The name of the placeholder
     */
    public function setMessage($message, $name = 'message'): void
    {
        $this->messages[$name] = $message;
    }

    public function getMessage($name = 'message')
    {
        return $this->messages[$name];
    }

    /**
     * Gets the progress bar start time.
     *
     * @return int The progress bar start time
     */
    public function getStartTime(): int
    {
        return $this->startTime;
    }

    /**
     * Gets the progress bar maximal steps.
     *
     * @return int The progress bar max steps
     */
    public function getMaxSteps(): ?int
    {
        return $this->max;
    }

    /**
     * Gets the current step position.
     *
     * @return int The progress bar step
     */
    public function getProgress(): int
    {
        return $this->step;
    }

    /**
     * Gets the progress bar step width.
     *
     * @return int The progress bar step width
     */
    private function getStepWidth(): int
    {
        return $this->stepWidth;
    }

    /**
     * Gets the current progress bar percent.
     *
     * @return float The current progress bar percent
     */
    public function getProgressPercent(): float
    {
        return $this->percent;
    }

    /**
     * Sets the progress bar width.
     *
     * @param int $size The progress bar size
     */
    public function setBarWidth($size): void
    {
        $this->barWidth = max(1, (int) $size);
    }

    /**
     * Gets the progress bar width.
     *
     * @return int The progress bar size
     */
    public function getBarWidth(): int
    {
        return $this->barWidth;
    }

    /**
     * Sets the bar character.
     *
     * @param string $char A character
     */
    public function setBarCharacter(?string $char): void
    {
        $this->barChar = $char;
    }

    /**
     * Gets the bar character.
     *
     * @return string A character
     */
    public function getBarCharacter(): string
    {
        if (null === $this->barChar) {
            return $this->max ? '=' : $this->emptyBarChar;
        }

        return $this->barChar;
    }

    /**
     * Sets the empty bar character.
     *
     * @param string $char A character
     */
    public function setEmptyBarCharacter(string $char): void
    {
        $this->emptyBarChar = $char;
    }

    /**
     * Gets the empty bar character.
     *
     * @return string A character
     */
    public function getEmptyBarCharacter(): string
    {
        return $this->emptyBarChar;
    }

    /**
     * Sets the progress bar character.
     *
     * @param string $char A character
     */
    public function setProgressCharacter(string $char): void
    {
        $this->progressChar = $char;
    }

    /**
     * Gets the progress bar character.
     *
     * @return string A character
     */
    public function getProgressCharacter(): string
    {
        return $this->progressChar;
    }

    /**
     * Sets the progress bar format.
     *
     * @param string $format The format
     */
    public function setFormat(string $format): void
    {
        $this->format = null;
        $this->internalFormat = $format;
    }

    /**
     * Sets the redraw frequency.
     *
     * @param int|float $freq The frequency in steps
     */
    public function setRedrawFrequency($freq): void
    {
        $this->redrawFreq = max((int) $freq, 1);
    }

    /**
     * Starts the progress output.
     *
     * @param int|null $max Number of steps to complete the bar (0 if indeterminate), null to leave unchanged
     */
    public function start($max = null): void
    {
        $this->startTime = time();
        $this->step = 0;
        $this->percent = 0.0;

        if (null !== $max) {
            $this->setMaxSteps($max);
        }

        $this->display();
    }

    /**
     * Advances the progress output X steps.
     *
     * @param int $step Number of steps to advance
     */
    public function advance($step = 1): void
    {
        $this->setProgress($this->step + $step);
    }

    /**
     * Sets whether to overwrite the progressbar, false for new line.
     *
     * @param bool $overwrite
     */
    public function setOverwrite($overwrite): void
    {
        $this->overwrite = (bool) $overwrite;
    }

    /**
     * Sets the current progress.
     *
     * @param int $step The current progress
     */
    public function setProgress($step): void
    {
        $step = (int) $step;

        if ($this->max && $step > $this->max) {
            $this->max = $step;
        } elseif ($step < 0) {
            $step = 0;
        }

        $prevPeriod = (int) ($this->step / $this->redrawFreq);
        $currPeriod = (int) ($step / $this->redrawFreq);
        $this->step = $step;
        $this->percent = $this->max ? (float) $this->step / $this->max : 0;
        if ($prevPeriod !== $currPeriod || $this->max === $step) {
            $this->display();
        }
    }

    /**
     * Finishes the progress output.
     */
    public function finish(): void
    {
        if (!$this->max) {
            $this->max = $this->step;
        }

        if ($this->step === $this->max && !$this->overwrite) {
            // prevent double 100% output
            return;
        }

        $this->setProgress($this->max);
    }

    /**
     * Outputs the current progress string.
     */
    public function display(): void
    {
        if (OutputInterface::VERBOSITY_QUIET === $this->output->getVerbosity()) {
            return;
        }

        if (null === $this->format) {
            $this->setRealFormat($this->internalFormat ?: $this->determineBestFormat());
        }

        $this->overwrite($this->buildLine());
    }

    /**
     * Removes the progress bar from the current line.
     *
     * This is useful if you wish to write some output
     * while a progress bar is running.
     * Call display() to show the progress bar again.
     */
    public function clear(): void
    {
        if (!$this->overwrite) {
            return;
        }

        if (null === $this->format) {
            $this->setRealFormat($this->internalFormat ?: $this->determineBestFormat());
        }

        $this->overwrite('');
    }

    /**
     * Sets the progress bar format.
     *
     * @param string $format The format
     */
    private function setRealFormat(string $format): void
    {
        // try to use the _nomax variant if available
        if (!$this->max && null !== self::getFormatDefinition($format.'_nomax')) {
            $this->format = self::getFormatDefinition($format.'_nomax');
        } elseif (null !== self::getFormatDefinition($format)) {
            $this->format = self::getFormatDefinition($format);
        } else {
            $this->format = $format;
        }

        $this->formatLineCount = substr_count($this->format, "\n");
    }

    /**
     * Sets the progress bar maximal steps.
     *
     * @param int $max The progress bar max steps
     */
    private function setMaxSteps($max): void
    {
        $this->max = max(0, (int) $max);
        $this->stepWidth = $this->max !== 0 ? Helper::strlen($this->max) : 4;
    }

    /**
     * Overwrites a previous message to the output.
     *
     * @param string $message The message
     */
    private function overwrite($message): void
    {
        if ($this->overwrite) {
            if (!$this->firstRun) {
                // Erase previous lines
                if ($this->formatLineCount > 0) {
                    $message = str_repeat("\x1B[1A\x1B[2K", $this->formatLineCount).$message;
                }

                // Move the cursor to the beginning of the line and erase the line
                $message = '[2K' . $message;
            }
        } elseif ($this->step > 0) {
            $message = \PHP_EOL.$message;
        }

        $this->firstRun = false;

        $this->output->write($message);
    }

    private function determineBestFormat()
    {
        switch ($this->output->getVerbosity()) {
            // OutputInterface::VERBOSITY_QUIET: display is disabled anyway
            case OutputInterface::VERBOSITY_VERBOSE:
                return $this->max ? 'verbose' : 'verbose_nomax';
            case OutputInterface::VERBOSITY_VERY_VERBOSE:
                return $this->max ? 'very_verbose' : 'very_verbose_nomax';
            case OutputInterface::VERBOSITY_DEBUG:
                return $this->max ? 'debug' : 'debug_nomax';
            default:
                return $this->max ? 'normal' : 'normal_nomax';
        }
    }

    private static function initPlaceholderFormatters(): array
    {
        return [
            'bar' => static function (self $bar, OutputInterface $output) : string {
                $completeBars = floor($bar->getMaxSteps() > 0 ? $bar->getProgressPercent() * $bar->getBarWidth() : $bar->getProgress() % $bar->getBarWidth());
                $display = str_repeat($bar->getBarCharacter(), $completeBars);
                if ($completeBars < $bar->getBarWidth()) {
                    $emptyBars = $bar->getBarWidth() - $completeBars - Helper::strlenWithoutDecoration($output->getFormatter(), $bar->getProgressCharacter());
                    $display .= $bar->getProgressCharacter().str_repeat($bar->getEmptyBarCharacter(), $emptyBars);
                }
                return $display;
            },
            'elapsed' => static fn(self $bar) => Helper::formatTime(time() - $bar->getStartTime()),
            'remaining' => static function (self $bar) {
                if ($bar->getMaxSteps() === 0) {
                    throw new LogicException('Unable to display the remaining time if the maximum number of steps is not set.');
                }
                if ($bar->getProgress() === 0) {
                    $remaining = 0;
                } else {
                    $remaining = round((time() - $bar->getStartTime()) / $bar->getProgress() * ($bar->getMaxSteps() - $bar->getProgress()));
                }
                return Helper::formatTime($remaining);
            },
            'estimated' => static function (self $bar) {
                if ($bar->getMaxSteps() === 0) {
                    throw new LogicException('Unable to display the estimated time if the maximum number of steps is not set.');
                }
                if ($bar->getProgress() === 0) {
                    $estimated = 0;
                } else {
                    $estimated = round((time() - $bar->getStartTime()) / $bar->getProgress() * $bar->getMaxSteps());
                }
                return Helper::formatTime($estimated);
            },
            'memory' => static fn(self $bar) => Helper::formatMemory(memory_get_usage(true)),
            'current' => static fn(self $bar): string => str_pad($bar->getProgress(), $bar->getStepWidth(), ' ', \STR_PAD_LEFT),
            'max' => static fn(self $bar): int => $bar->getMaxSteps(),
            'percent' => static fn(self $bar): float => floor($bar->getProgressPercent() * 100),
        ];
    }

    private static function initFormats(): array
    {
        return [
            'normal' => ' %current%/%max% [%bar%] %percent:3s%%',
            'normal_nomax' => ' %current% [%bar%]',

            'verbose' => ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%',
            'verbose_nomax' => ' %current% [%bar%] %elapsed:6s%',

            'very_verbose' => ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%',
            'very_verbose_nomax' => ' %current% [%bar%] %elapsed:6s%',

            'debug' => ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%',
            'debug_nomax' => ' %current% [%bar%] %elapsed:6s% %memory:6s%',
        ];
    }

    /**
     * @return string
     */
    private function buildLine(): string|array|null
    {
        $regex = "{%([a-z\-_]+)(?:\:([^%]+))?%}i";
        $callback = function (array $matches) {
            if (($formatter = $this::getPlaceholderFormatterDefinition($matches[1])) !== null) {
                $text = \call_user_func($formatter, $this, $this->output);
            } elseif (isset($this->messages[$matches[1]])) {
                $text = $this->messages[$matches[1]];
            } else {
                return $matches[0];
            }

            if (isset($matches[2])) {
                $text = sprintf('%'.$matches[2], $text);
            }

            return $text;
        };
        $line = preg_replace_callback($regex, $callback, $this->format);

        // gets string length for each sub line with multiline format
        $linesLength = array_map(fn($subLine) => Helper::strlenWithoutDecoration($this->output->getFormatter(), rtrim($subLine, "\r")), explode("\n", $line));

        $linesWidth = max($linesLength);

        $terminalWidth = $this->terminal->getWidth();
        if ($linesWidth <= $terminalWidth) {
            return $line;
        }

        $this->setBarWidth($this->barWidth - $linesWidth + $terminalWidth);

        return preg_replace_callback($regex, $callback, $this->format);
    }
}
