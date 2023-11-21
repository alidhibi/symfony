<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Intl;

class IntlTest extends TestCase
{
    private $defaultLocale;

    protected function setUp()
    {
        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown()
    {
        \Locale::setDefault($this->defaultLocale);
    }

    /**
     * @requires extension intl
     */
    public function testIsExtensionLoadedChecksIfIntlExtensionIsLoaded(): void
    {
        $this->assertTrue(Intl::isExtensionLoaded());
    }

    public function testGetCurrencyBundleCreatesTheCurrencyBundle(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Intl\ResourceBundle\CurrencyBundleInterface::class, Intl::getCurrencyBundle());
    }

    public function testGetLanguageBundleCreatesTheLanguageBundle(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Intl\ResourceBundle\LanguageBundleInterface::class, Intl::getLanguageBundle());
    }

    public function testGetLocaleBundleCreatesTheLocaleBundle(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Intl\ResourceBundle\LocaleBundleInterface::class, Intl::getLocaleBundle());
    }

    public function testGetRegionBundleCreatesTheRegionBundle(): void
    {
        $this->assertInstanceOf(\Symfony\Component\Intl\ResourceBundle\RegionBundleInterface::class, Intl::getRegionBundle());
    }

    public function testGetIcuVersionReadsTheVersionOfInstalledIcuLibrary(): void
    {
        $this->assertStringMatchesFormat('%d.%d', Intl::getIcuVersion());
    }

    public function testGetIcuDataVersionReadsTheVersionOfInstalledIcuData(): void
    {
        $this->assertStringMatchesFormat('%d.%d', Intl::getIcuDataVersion());
    }

    public function testGetIcuStubVersionReadsTheVersionOfBundledStubs(): void
    {
        $this->assertStringMatchesFormat('%d.%d', Intl::getIcuStubVersion());
    }

    public function testGetDataDirectoryReturnsThePathToIcuData(): void
    {
        $this->assertDirectoryExists(Intl::getDataDirectory());
    }

    /**
     * @requires extension intl
     */
    public function testLocaleAliasesAreLoaded(): void
    {
        \Locale::setDefault('zh_TW');
        $countryNameZhTw = Intl::getRegionBundle()->getCountryName('AD');

        \Locale::setDefault('zh_Hant_TW');
        $countryNameHantZhTw = Intl::getRegionBundle()->getCountryName('AD');

        \Locale::setDefault('zh');
        $countryNameZh = Intl::getRegionBundle()->getCountryName('AD');

        $this->assertSame($countryNameZhTw, $countryNameHantZhTw, 'zh_TW is an alias to zh_Hant_TW');
        $this->assertNotSame($countryNameZh, $countryNameZhTw, 'zh_TW does not fall back to zh');
    }
}
