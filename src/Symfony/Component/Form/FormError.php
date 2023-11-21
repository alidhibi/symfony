<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\BadMethodCallException;

/**
 * Wraps errors in forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormError implements \Serializable
{
    protected $messageTemplate;

    protected array $messageParameters;

    protected $messagePluralization;

    private readonly string $message;

    private $cause;

    /**
     * The form that spawned this error.
     *
     */
    private ?\Symfony\Component\Form\FormInterface $origin = null;

    /**
     * Any array key in $messageParameters will be used as a placeholder in
     * $messageTemplate.
     *
     * @param string      $message              The translated error message
     * @param string|null $messageTemplate      The template for the error message
     * @param array       $messageParameters    The parameters that should be
     *                                          substituted in the message template
     * @param int|null    $messagePluralization The value for error message pluralization
     * @param mixed       $cause                The cause of the error
     *
     * @see \Symfony\Component\Translation\Translator
     */
    public function __construct($message, $messageTemplate = null, array $messageParameters = [], $messagePluralization = null, $cause = null)
    {
        $this->message = (string) $message;
        $this->messageTemplate = $messageTemplate ?: $message;
        $this->messageParameters = $messageParameters;
        $this->messagePluralization = $messagePluralization;
        $this->cause = $cause;
    }

    /**
     * Returns the error message.
     *
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the error message template.
     *
     * @return string
     */
    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    /**
     * Returns the parameters to be inserted in the message template.
     *
     * @return array
     */
    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    /**
     * Returns the value for error message pluralization.
     *
     * @return int|null
     */
    public function getMessagePluralization()
    {
        return $this->messagePluralization;
    }

    /**
     * Returns the cause of this error.
     *
     * @return mixed The cause of this error
     */
    public function getCause()
    {
        return $this->cause;
    }

    /**
     * Sets the form that caused this error.
     *
     * This method must only be called once.
     *
     * @param FormInterface $origin The form that caused this error
     *
     * @throws BadMethodCallException If the method is called more than once
     */
    public function setOrigin(FormInterface $origin): void
    {
        if ($this->origin instanceof \Symfony\Component\Form\FormInterface) {
            throw new BadMethodCallException('setOrigin() must only be called once.');
        }

        $this->origin = $origin;
    }

    /**
     * Returns the form that caused this error.
     *
     * @return FormInterface|null The form that caused this error
     */
    public function getOrigin(): ?\Symfony\Component\Form\FormInterface
    {
        return $this->origin;
    }

    /**
     * @internal
     */
    public function serialize(): string
    {
        return serialize([
            $this->message,
            $this->messageTemplate,
            $this->messageParameters,
            $this->messagePluralization,
            $this->cause,
        ]);
    }

    /**
     * @internal
     */
    public function unserialize($serialized): void
    {
        list($this->message, $this->messageTemplate, $this->messageParameters, $this->messagePluralization, $this->cause) = unserialize($serialized, ['allowed_classes' => false]);
    }
}
