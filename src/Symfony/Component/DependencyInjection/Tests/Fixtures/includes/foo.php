<?php

namespace Bar;

class FooClass
{
    public $foo;

    public $moo;

    public $bar;

    public $initialized = false;

    public $configured = false;

    public $called = false;

    public $arguments = [];

    public function __construct($arguments = [])
    {
        $this->arguments = $arguments;
    }

    public static function getInstance($arguments = []): self
    {
        $obj = new self($arguments);
        $obj->called = true;

        return $obj;
    }

    public function initialize(): void
    {
        $this->initialized = true;
    }

    public function configure(): void
    {
        $this->configured = true;
    }

    public function setBar($value = null): void
    {
        $this->bar = $value;
    }
}
