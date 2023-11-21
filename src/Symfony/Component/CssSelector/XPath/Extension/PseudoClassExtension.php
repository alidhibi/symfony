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

use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\XPath\XPathExpr;

/**
 * XPath expression translator pseudo-class extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class PseudoClassExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getPseudoClassTranslators(): array
    {
        return [
            'root' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateRoot($xpath),
            'first-child' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateFirstChild($xpath),
            'last-child' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateLastChild($xpath),
            'first-of-type' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateFirstOfType($xpath),
            'last-of-type' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateLastOfType($xpath),
            'only-child' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateOnlyChild($xpath),
            'only-of-type' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateOnlyOfType($xpath),
            'empty' => fn(\Symfony\Component\CssSelector\XPath\XPathExpr $xpath): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateEmpty($xpath),
        ];
    }

    /**
     * @return XPathExpr
     */
    public function translateRoot(XPathExpr $xpath)
    {
        return $xpath->addCondition('not(parent::*)');
    }

    /**
     * @return XPathExpr
     */
    public function translateFirstChild(XPathExpr $xpath)
    {
        return $xpath
            ->addStarPrefix()
            ->addNameTest()
            ->addCondition('position() = 1');
    }

    /**
     * @return XPathExpr
     */
    public function translateLastChild(XPathExpr $xpath)
    {
        return $xpath
            ->addStarPrefix()
            ->addNameTest()
            ->addCondition('position() = last()');
    }

    /**
     * @return XPathExpr
     *
     * @throws ExpressionErrorException
     */
    public function translateFirstOfType(XPathExpr $xpath)
    {
        if ('*' === $xpath->getElement()) {
            throw new ExpressionErrorException('"*:first-of-type" is not implemented.');
        }

        return $xpath
            ->addStarPrefix()
            ->addCondition('position() = 1');
    }

    /**
     * @return XPathExpr
     *
     * @throws ExpressionErrorException
     */
    public function translateLastOfType(XPathExpr $xpath)
    {
        if ('*' === $xpath->getElement()) {
            throw new ExpressionErrorException('"*:last-of-type" is not implemented.');
        }

        return $xpath
            ->addStarPrefix()
            ->addCondition('position() = last()');
    }

    /**
     * @return XPathExpr
     */
    public function translateOnlyChild(XPathExpr $xpath)
    {
        return $xpath
            ->addStarPrefix()
            ->addNameTest()
            ->addCondition('last() = 1');
    }

    /**
     * @return XPathExpr
     *
     * @throws ExpressionErrorException
     */
    public function translateOnlyOfType(XPathExpr $xpath)
    {
        $element = $xpath->getElement();

        if ('*' === $element) {
            throw new ExpressionErrorException('"*:only-of-type" is not implemented.');
        }

        return $xpath->addCondition(sprintf('count(preceding-sibling::%s)=0 and count(following-sibling::%s)=0', $element, $element));
    }

    /**
     * @return XPathExpr
     */
    public function translateEmpty(XPathExpr $xpath)
    {
        return $xpath->addCondition('not(*) and not(string-length())');
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'pseudo-class';
    }
}
