<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper\Fixtures;

use Symfony\Component\Translation\TranslatorInterface;

class StubTranslator implements TranslatorInterface
{
    public function trans(string $id, array $parameters = [], $domain = null, $locale = null): string
    {
        return '[trans]'.$id.'[/trans]';
    }

    public function transChoice(string $id, $number, array $parameters = [], $domain = null, $locale = null): string
    {
        return '[trans]'.$id.'[/trans]';
    }

    public function setLocale($locale): void
    {
    }

    public function getLocale(): void
    {
    }
}
