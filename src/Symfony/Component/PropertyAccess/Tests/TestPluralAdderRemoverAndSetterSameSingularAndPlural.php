<?php

namespace Symfony\Component\PropertyAccess\Tests;

class TestPluralAdderRemoverAndSetterSameSingularAndPlural
{
    private array $aircraft = [];

    public function getAircraft(): array
    {
        return $this->aircraft;
    }

    public function setAircraft(array $aircraft): void
    {
        $this->aircraft = ['plane'];
    }

    public function addAircraft($aircraft): void
    {
        $this->aircraft[] = $aircraft;
    }

    public function removeAircraft($aircraft): void
    {
        $this->aircraft = array_diff($this->aircraft, [$aircraft]);
    }
}
