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

use Symfony\Component\CssSelector\Node;
use Symfony\Component\CssSelector\XPath\Translator;
use Symfony\Component\CssSelector\XPath\XPathExpr;

/**
 * XPath expression translator node extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class NodeExtension extends AbstractExtension
{
    final const ELEMENT_NAME_IN_LOWER_CASE = 1;

    final const ATTRIBUTE_NAME_IN_LOWER_CASE = 2;

    final const ATTRIBUTE_VALUE_IN_LOWER_CASE = 4;

    private $flags;

    /**
     * @param int $flags
     */
    public function __construct($flags = 0)
    {
        $this->flags = $flags;
    }

    /**
     * @param int  $flag
     * @param bool $on
     *
     * @return $this
     */
    public function setFlag($flag, $on): static
    {
        if ($on && !$this->hasFlag($flag)) {
            $this->flags += $flag;
        }

        if (!$on && $this->hasFlag($flag)) {
            $this->flags -= $flag;
        }

        return $this;
    }

    /**
     * @param int $flag
     *
     */
    public function hasFlag($flag): bool
    {
        return (bool) ($this->flags & $flag);
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeTranslators(): array
    {
        return [
            'Selector' => fn(\Symfony\Component\CssSelector\Node\SelectorNode $node, \Symfony\Component\CssSelector\XPath\Translator $translator): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateSelector($node, $translator),
            'CombinedSelector' => fn(\Symfony\Component\CssSelector\Node\CombinedSelectorNode $node, \Symfony\Component\CssSelector\XPath\Translator $translator): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateCombinedSelector($node, $translator),
            'Negation' => fn(\Symfony\Component\CssSelector\Node\NegationNode $node, \Symfony\Component\CssSelector\XPath\Translator $translator): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateNegation($node, $translator),
            'Function' => fn(\Symfony\Component\CssSelector\Node\FunctionNode $node, \Symfony\Component\CssSelector\XPath\Translator $translator): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateFunction($node, $translator),
            'Pseudo' => fn(\Symfony\Component\CssSelector\Node\PseudoNode $node, \Symfony\Component\CssSelector\XPath\Translator $translator): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translatePseudo($node, $translator),
            'Attribute' => fn(\Symfony\Component\CssSelector\Node\AttributeNode $node, \Symfony\Component\CssSelector\XPath\Translator $translator): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateAttribute($node, $translator),
            'Class' => fn(\Symfony\Component\CssSelector\Node\ClassNode $node, \Symfony\Component\CssSelector\XPath\Translator $translator): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateClass($node, $translator),
            'Hash' => fn(\Symfony\Component\CssSelector\Node\HashNode $node, \Symfony\Component\CssSelector\XPath\Translator $translator): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateHash($node, $translator),
            'Element' => fn(\Symfony\Component\CssSelector\Node\ElementNode $node): \Symfony\Component\CssSelector\XPath\XPathExpr => $this->translateElement($node),
        ];
    }

    /**
     * @return XPathExpr
     */
    public function translateSelector(Node\SelectorNode $node, Translator $translator)
    {
        return $translator->nodeToXPath($node->getTree());
    }

    /**
     * @return XPathExpr
     */
    public function translateCombinedSelector(Node\CombinedSelectorNode $node, Translator $translator)
    {
        return $translator->addCombination($node->getCombinator(), $node->getSelector(), $node->getSubSelector());
    }

    /**
     * @return XPathExpr
     */
    public function translateNegation(Node\NegationNode $node, Translator $translator)
    {
        $xpath = $translator->nodeToXPath($node->getSelector());
        $subXpath = $translator->nodeToXPath($node->getSubSelector());
        $subXpath->addNameTest();

        if ($subXpath->getCondition() !== '' && $subXpath->getCondition() !== '0') {
            return $xpath->addCondition(sprintf('not(%s)', $subXpath->getCondition()));
        }

        return $xpath->addCondition('0');
    }

    /**
     * @return XPathExpr
     */
    public function translateFunction(Node\FunctionNode $node, Translator $translator)
    {
        $xpath = $translator->nodeToXPath($node->getSelector());

        return $translator->addFunction($xpath, $node);
    }

    /**
     * @return XPathExpr
     */
    public function translatePseudo(Node\PseudoNode $node, Translator $translator)
    {
        $xpath = $translator->nodeToXPath($node->getSelector());

        return $translator->addPseudoClass($xpath, $node->getIdentifier());
    }

    /**
     * @return XPathExpr
     */
    public function translateAttribute(Node\AttributeNode $node, Translator $translator)
    {
        $name = $node->getAttribute();
        $safe = $this->isSafeName($name);

        if ($this->hasFlag(self::ATTRIBUTE_NAME_IN_LOWER_CASE)) {
            $name = strtolower($name);
        }

        if ($node->getNamespace() !== '' && $node->getNamespace() !== '0') {
            $name = sprintf('%s:%s', $node->getNamespace(), $name);
            $safe = $safe && $this->isSafeName($node->getNamespace());
        }

        $attribute = $safe ? '@'.$name : sprintf('attribute::*[name() = %s]', Translator::getXpathLiteral($name));
        $value = $node->getValue();
        $xpath = $translator->nodeToXPath($node->getSelector());

        if ($this->hasFlag(self::ATTRIBUTE_VALUE_IN_LOWER_CASE)) {
            $value = strtolower($value);
        }

        return $translator->addAttributeMatching($xpath, $node->getOperator(), $attribute, $value);
    }

    /**
     * @return XPathExpr
     */
    public function translateClass(Node\ClassNode $node, Translator $translator)
    {
        $xpath = $translator->nodeToXPath($node->getSelector());

        return $translator->addAttributeMatching($xpath, '~=', '@class', $node->getName());
    }

    /**
     * @return XPathExpr
     */
    public function translateHash(Node\HashNode $node, Translator $translator)
    {
        $xpath = $translator->nodeToXPath($node->getSelector());

        return $translator->addAttributeMatching($xpath, '=', '@id', $node->getId());
    }

    public function translateElement(Node\ElementNode $node): \Symfony\Component\CssSelector\XPath\XPathExpr
    {
        $element = $node->getElement();

        if ($this->hasFlag(self::ELEMENT_NAME_IN_LOWER_CASE)) {
            $element = strtolower($element);
        }

        if ($element) {
            $safe = $this->isSafeName($element);
        } else {
            $element = '*';
            $safe = true;
        }

        if ($node->getNamespace()) {
            $element = sprintf('%s:%s', $node->getNamespace(), $element);
            $safe = $safe && $this->isSafeName($node->getNamespace());
        }

        $xpath = new XPathExpr('', $element);

        if (!$safe) {
            $xpath->addNameTest();
        }

        return $xpath;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'node';
    }

    /**
     * Tests if given name is safe.
     *
     * @param string $name
     *
     */
    private function isSafeName($name): bool
    {
        return 0 < preg_match('~^[a-zA-Z_][a-zA-Z0-9_.-]*$~', $name);
    }
}
