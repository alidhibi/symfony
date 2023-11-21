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

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.4 and will be removed in 4.0. Use the Process class instead.', ProcessBuilder::class), \E_USER_DEPRECATED);

use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\Exception\LogicException;

/**
 * @author Kris Wallsmith <kris@symfony.com>
 *
 * @deprecated since version 3.4, to be removed in 4.0. Use the Process class instead.
 */
class ProcessBuilder
{
    private array $arguments;

    private $cwd;

    private array $env = [];

    private $input;

    private $timeout = 60;

    private $options;

    private bool $inheritEnv = true;

    private array $prefix = [];

    private bool $outputDisabled = false;

    /**
     * @param string[] $arguments An array of arguments
     */
    public function __construct(array $arguments = [])
    {
        $this->arguments = $arguments;
    }

    /**
     * Creates a process builder instance.
     *
     * @param string[] $arguments An array of arguments
     *
     */
    public static function create(array $arguments = []): static
    {
        return new static($arguments);
    }

    /**
     * Adds an unescaped argument to the command string.
     *
     * @param string $argument A command argument
     *
     * @return $this
     */
    public function add($argument): static
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * Adds a prefix to the command string.
     *
     * The prefix is preserved when resetting arguments.
     *
     * @param string|array $prefix A command prefix or an array of command prefixes
     *
     * @return $this
     */
    public function setPrefix($prefix): static
    {
        $this->prefix = \is_array($prefix) ? $prefix : [$prefix];

        return $this;
    }

    /**
     * Sets the arguments of the process.
     *
     * Arguments must not be escaped.
     * Previous arguments are removed.
     *
     * @param string[] $arguments
     *
     * @return $this
     */
    public function setArguments(array $arguments): static
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Sets the working directory.
     *
     * @param string|null $cwd The working directory
     *
     * @return $this
     */
    public function setWorkingDirectory($cwd): static
    {
        $this->cwd = $cwd;

        return $this;
    }

    /**
     * Sets whether environment variables will be inherited or not.
     *
     *
     * @return $this
     */
    public function inheritEnvironmentVariables(bool $inheritEnv = true): static
    {
        $this->inheritEnv = $inheritEnv;

        return $this;
    }

    /**
     * Sets an environment variable.
     *
     * Setting a variable overrides its previous value. Use `null` to unset a
     * defined environment variable.
     *
     * @param string      $name  The variable name
     * @param string|null $value The variable value
     *
     * @return $this
     */
    public function setEnv($name, $value): static
    {
        $this->env[$name] = $value;

        return $this;
    }

    /**
     * Adds a set of environment variables.
     *
     * Already existing environment variables with the same name will be
     * overridden by the new values passed to this method. Pass `null` to unset
     * a variable.
     *
     * @param array $variables The variables
     *
     * @return $this
     */
    public function addEnvironmentVariables(array $variables): static
    {
        $this->env = array_replace($this->env, $variables);

        return $this;
    }

    /**
     * Sets the input of the process.
     *
     * @param resource|string|int|float|bool|\Traversable|null $input The input content
     *
     * @return $this
     *
     * @throws InvalidArgumentException In case the argument is invalid
     */
    public function setInput(mixed $input): static
    {
        $this->input = ProcessUtils::validateInput(__METHOD__, $input);

        return $this;
    }

    /**
     * Sets the process timeout.
     *
     * To disable the timeout, set this value to null.
     *
     * @param float|null $timeout
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function setTimeout($timeout): static
    {
        if (null === $timeout) {
            $this->timeout = null;

            return $this;
        }

        $timeout = (float) $timeout;

        if ($timeout < 0) {
            throw new InvalidArgumentException('The timeout value must be a valid positive integer or float number.');
        }

        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Adds a proc_open option.
     *
     * @param string $name  The option name
     * @param string $value The option value
     *
     * @return $this
     */
    public function setOption($name, $value): static
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * Disables fetching output and error output from the underlying process.
     *
     * @return $this
     */
    public function disableOutput(): static
    {
        $this->outputDisabled = true;

        return $this;
    }

    /**
     * Enables fetching output and error output from the underlying process.
     *
     * @return $this
     */
    public function enableOutput(): static
    {
        $this->outputDisabled = false;

        return $this;
    }

    /**
     * Creates a Process instance and returns it.
     *
     *
     * @throws LogicException In case no arguments have been provided
     */
    public function getProcess(): \Symfony\Component\Process\Process
    {
        if ([] === $this->prefix && [] === $this->arguments) {
            throw new LogicException('You must add() command arguments before calling getProcess().');
        }

        $arguments = array_merge($this->prefix, $this->arguments);
        $process = new Process($arguments, $this->cwd, $this->env, $this->input, $this->timeout, $this->options);
        // to preserve the BC with symfony <3.3, we convert the array structure
        // to a string structure to avoid the prefixing with the exec command
        $process->setCommandLine($process->getCommandLine());

        if ($this->inheritEnv) {
            $process->inheritEnvironmentVariables();
        }

        if ($this->outputDisabled) {
            $process->disableOutput();
        }

        return $process;
    }
}
