<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class NamedArgumentsDummy
{
    public function setApiKey($apiKey): void
    {
    }

    public function setSensitiveClass(CaseSensitiveClass $c): void
    {
    }
}
