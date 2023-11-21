<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Allows to filter a Response object.
 *
 * You can call getResponse() to retrieve the current response. With
 * setResponse() you can set a new response that will be returned to the
 * browser.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FilterResponseEvent extends KernelEvent
{
    private \Symfony\Component\HttpFoundation\Response $response;

    public function __construct(HttpKernelInterface $kernel, Request $request, $requestType, Response $response)
    {
        parent::__construct($kernel, $request, $requestType);

        $this->setResponse($response);
    }

    /**
     * Returns the current response object.
     *
     */
    public function getResponse(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->response;
    }

    /**
     * Sets a new response object.
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
