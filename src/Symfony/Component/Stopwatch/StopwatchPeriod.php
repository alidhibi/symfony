<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch;

/**
 * Represents an Period for an Event.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class StopwatchPeriod
{
    private readonly float|int $start;

    private readonly float|int $end;

    private readonly int $memory;

    /**
     * @param int|float $start         The relative time of the start of the period (in milliseconds)
     * @param int|float $end           The relative time of the end of the period (in milliseconds)
     * @param bool      $morePrecision If true, time is stored as float to keep the original microsecond precision
     */
    public function __construct($start, $end, $morePrecision = false)
    {
        $this->start = $morePrecision ? (float) $start : (int) $start;
        $this->end = $morePrecision ? (float) $end : (int) $end;
        $this->memory = memory_get_usage(true);
    }

    /**
     * Gets the relative time of the start of the period.
     *
     * @return int|float The time (in milliseconds)
     */
    public function getStartTime(): int|float
    {
        return $this->start;
    }

    /**
     * Gets the relative time of the end of the period.
     *
     * @return int|float The time (in milliseconds)
     */
    public function getEndTime(): int|float
    {
        return $this->end;
    }

    /**
     * Gets the time spent in this period.
     *
     * @return int|float The period duration (in milliseconds)
     */
    public function getDuration(): float|int
    {
        return $this->end - $this->start;
    }

    /**
     * Gets the memory usage.
     *
     * @return int The memory usage (in bytes)
     */
    public function getMemory(): int
    {
        return $this->memory;
    }
}
