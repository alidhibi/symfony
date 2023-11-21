<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\RouterInterface;

class InjectedFlashbagSessionController
{
    private \Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface $flashBag;

    private \Symfony\Component\Routing\RouterInterface $router;

    public function __construct(
        FlashBagInterface $flashBag,
        RouterInterface $router
    ) {
        $this->flashBag = $flashBag;
        $this->router = $router;
    }

    public function setFlashAction(Request $request, $message)
    {
        $this->flashBag->add('notice', $message);

        return new RedirectResponse($this->router->generate('session_showflash'));
    }
}
