<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WebDebugToolbarListenerTest extends TestCase
{
    /**
     * @dataProvider getInjectToolbarTests
     */
    public function testInjectToolbar(string $content, string $expected): void
    {
        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $m = new \ReflectionMethod($listener, 'injectToolbar');
        $m->setAccessible(true);

        $response = new Response($content);

        $m->invoke($listener, $response, Request::create('/'), ['csp_script_nonce' => 'scripto', 'csp_style_nonce' => 'stylo']);
        $this->assertEquals($expected, $response->getContent());
    }

    public function getInjectToolbarTests(): array
    {
        return [
            ['<html><head></head><body></body></html>', "<html><head></head><body>\nWDT\n</body></html>"],
            ['<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            </body>
            </html>', "<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            \nWDT\n</body>
            </html>"],
        ];
    }

    /**
     * @dataProvider provideRedirects
     */
    public function testHtmlRedirectionIsIntercepted(int $statusCode, bool $hasSession): void
    {
        $response = new Response('Some content', $statusCode);
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html', $hasSession), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock('Redirection'), true);
        $listener->onKernelResponse($event);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Redirection', $response->getContent());
    }

    public function testNonHtmlRedirectionIsNotIntercepted(): void
    {
        $response = new Response('Some content', '301');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'json', true), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock('Redirection'), true);
        $listener->onKernelResponse($event);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('Some content', $response->getContent());
    }

    public function testToolbarIsInjected(): void
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals("<html><head></head><body>\nWDT\n</body></html>", $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnNonHtmlContentType(): void
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $response->headers->set('Content-Type', 'text/xml');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnContentDispositionAttachment(): void
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $response->headers->set('Content-Disposition', 'attachment; filename=test.html');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     * @dataProvider provideRedirects
     */
    public function testToolbarIsNotInjectedOnRedirection(int $statusCode, bool $hasSession): void
    {
        $response = new Response('<html><head></head><body></body></html>', $statusCode);
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html', $hasSession), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    public function provideRedirects(): array
    {
        return [
            [301, true],
            [302, true],
            [301, false],
            [302, false],
        ];
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedWhenThereIsNoNoXDebugTokenResponseHeader(): void
    {
        $response = new Response('<html><head></head><body></body></html>');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedWhenOnSubRequest(): void
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::SUB_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnIncompleteHtmlResponses(): void
    {
        $response = new Response('<div>Some content</div>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<div>Some content</div>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnXmlHttpRequests(): void
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(true), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnNonHtmlRequests(): void
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'json'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    public function testXDebugUrlHeader(): void
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->getUrlGeneratorMock();
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => 'xxxxxxxx'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://mydomain.com/_profiler/xxxxxxxx')
        ;

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, 'bottom', $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('http://mydomain.com/_profiler/xxxxxxxx', $response->headers->get('X-Debug-Token-Link'));
    }

    public function testThrowingUrlGenerator(): void
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->getUrlGeneratorMock();
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => 'xxxxxxxx'])
            ->willThrowException(new \Exception('foo'))
        ;

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, 'bottom', $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('Exception: foo', $response->headers->get('X-Debug-Error'));
    }

    public function testThrowingErrorCleanup(): void
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->getUrlGeneratorMock();
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => 'xxxxxxxx'])
            ->willThrowException(new \Exception("This\nmultiline\r\ntabbed text should\tcome out\r on\n \ta single plain\r\nline"))
        ;

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, 'bottom', $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('Exception: This multiline tabbed text should come out on a single plain line', $response->headers->get('X-Debug-Error'));
    }

    protected function getRequestMock($isXmlHttpRequest = false, $requestFormat = 'html', $hasSession = true)
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->setMethods(['getSession', 'isXmlHttpRequest', 'getRequestFormat'])->disableOriginalConstructor()->getMock();
        $request->expects($this->any())
            ->method('isXmlHttpRequest')
            ->willReturn($isXmlHttpRequest);
        $request->expects($this->any())
            ->method('getRequestFormat')
            ->willReturn($requestFormat);

        $request->headers = new HeaderBag();

        if ($hasSession) {
            $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')->disableOriginalConstructor()->getMock();
            $request->expects($this->any())
                ->method('getSession')
                ->willReturn($session);
        }

        return $request;
    }

    protected function getTwigMock($render = 'WDT')
    {
        $templating = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $templating->expects($this->any())
            ->method('render')
            ->willReturn($render);

        return $templating;
    }

    protected function getUrlGeneratorMock()
    {
        return $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();
    }

    protected function getKernelMock()
    {
        return $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();
    }
}
