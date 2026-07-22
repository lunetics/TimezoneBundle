<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\EventListener;

use Lunetics\TimezoneBundle\Context\CurrentTimezoneProvider;
use Lunetics\TimezoneBundle\Event\TimezoneResolvedEvent;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolverChain;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ResolveTimezoneListener implements EventSubscriberInterface
{
    public function __construct(private TimezoneResolverChain $resolver, private EventDispatcherInterface $dispatcher)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 1]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $resolution = $this->resolver->resolve($request);
        $request->attributes->set(CurrentTimezoneProvider::RESOLUTION_ATTRIBUTE, $resolution);
        $this->dispatcher->dispatch(new TimezoneResolvedEvent($request, $resolution));
    }
}
