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

use Symfony\Component\EventDispatcher\Event;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormEvent extends Event
{
    private readonly \Symfony\Component\Form\FormInterface $form;

    protected $data;

    /**
     * @param FormInterface $form The associated form
     * @param mixed         $data The data
     */
    public function __construct(FormInterface $form, $data)
    {
        $this->form = $form;
        $this->data = $data;
    }

    /**
     * Returns the form at the source of the event.
     *
     */
    public function getForm(): \Symfony\Component\Form\FormInterface
    {
        return $this->form;
    }

    /**
     * Returns the data associated with this event.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Allows updating with some filtered data.
     *
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }
}
