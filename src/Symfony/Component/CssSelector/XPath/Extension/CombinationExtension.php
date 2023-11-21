<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\XPath\Extension;

use Symfony\Component\CssSelector\XPath\XPathExpr;

/**
 * XPath expression translator combination extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class CombinationExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getCombinationTranslators(): array
    {
        return [
            ' ' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath, \Symfony\Component\CssSelector\XPath\XPathExpr $combinedXpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateDescendant($xpath, $combinedXpath),
            '>' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath, \Symfony\Component\CssSelector\XPath\XPathExpr $combinedXpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateChild($xpath, $combinedXpath),
            '+' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath, \Symfony\Component\CssSelector\XPath\XPathExpr $combinedXpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateDirectAdjacent($xpath, $combinedXpath),
            '~' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath, \Symfony\Component\CssSelector\XPath\XPathExpr $combinedXpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateIndirectAdjacent($xpath, $combinedXpath),
        ];
    }

    /**
     * @return XPathExpr
     */
    public function translateDescendant(XPathExpr $xpath, XPathExpr $combinedXpath)
    {
        return $xpath->join('/descendant-or-self::*/', $combinedXpath);
    }

    /**
     * @return XPathExpr
     */
    public function translateChild(XPathExpr $xpath, XPathExpr $combinedXpath)
    {
        return $xpath->join('/', $combinedXpath);
    }

    /**
     * @return XPathExpr
     */
    public function translateDirectAdjacent(XPathExpr $xpath, XPathExpr $combinedXpath)
    {
        return $xpath
            ->join('/following-sibling::', $combinedXpath)
            ->addNameTest()
            ->addCondition('position() = 1');
    }

    /**
     * @return XPathExpr
     */
    public function translateIndirectAdjacent(XPathExpr $xpath, XPathExpr $combinedXpath)
    {
        return $xpath->join('/following-sibling::', $combinedXpath);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'combination';
    }
}
