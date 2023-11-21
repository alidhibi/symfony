<?php

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class ProjectUrlMatcher extends Symfony\Component\Routing\Matcher\UrlMatcher
{
    public function __construct(RequestContext $context)
    {
        $this->context = $context;
    }

    public function match($rawPathinfo): void
    {
        $allow = [];
        $pathinfo = rawurldecode($rawPathinfo);
        $context = $this->context;
        $this->request ?: $this->createRequest($pathinfo);
        $requestMethod = $context->getMethod();
        $canonicalMethod = $requestMethod;

        if ('HEAD' === $requestMethod) {
            $canonicalMethod = 'GET';
        }

        if ('/' === $pathinfo && !$allow) {
            throw new Symfony\Component\Routing\Exception\NoConfigurationException();
        }

        throw [] !== $allow ? new MethodNotAllowedException(array_unique($allow)) : new ResourceNotFoundException();
    }
}
