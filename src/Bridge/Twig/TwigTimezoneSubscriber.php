<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\Twig;

use Lunetics\TimezoneBundle\Event\TimezoneResolvedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class TwigTimezoneSubscriber implements EventSubscriberInterface
{
    public function __construct(private TwigTimezoneScope $scope)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TimezoneResolvedEvent::class => 'onTimezoneResolved',
            KernelEvents::FINISH_REQUEST => 'onKernelFinishRequest',
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onTimezoneResolved(TimezoneResolvedEvent $event): void
    {
        $this->scope->enter($event->resolution->timezone);
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->scope->leave();
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $this->scope->reset();
    }
}
