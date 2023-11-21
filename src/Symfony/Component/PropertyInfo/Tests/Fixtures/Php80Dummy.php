<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class Php80Dummy
{
    public function getFoo(): array|null
    {
    }

    public function setBar(int|null $bar): void
    {
    }

    public function setTimeout(int|float $timeout): void
    {
    }

    public function getOptional(): int|float|null
    {
    }

    public function setString(string|\Stringable $string): void
    {
    }

    public function setPayload(mixed $payload): void
    {
    }

    public function getData(): mixed
    {
    }
}
