<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\EventListener;

use Lunetics\TimezoneBundle\Controller\BrowserTimezoneController;
use Lunetics\TimezoneBundle\Exception\PersistenceFailureExceptionInterface;
use Lunetics\TimezoneBundle\Resolution\PersistenceFailureStrategy;
use Lunetics\TimezoneBundle\Resolver\StoredPreferenceTimezoneResolver;
use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class InvalidPreferenceCleanupListener implements EventSubscriberInterface
{
    public const PREFERENCE_CLEARED_ATTRIBUTE = '_lunetics_timezone.preference_cleared';

    public function __construct(private TimezonePreferenceStorageInterface $storage, private PersistenceFailureStrategy $failureStrategy)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (true === $request->attributes->get(BrowserTimezoneController::PREFERENCE_WRITTEN_ATTRIBUTE)) {
            return;
        }

        $read = $request->attributes->get(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE);
        if (!$read instanceof \Lunetics\TimezoneBundle\Storage\TimezonePreferenceRead || !in_array($read->status, [PreferenceReadStatus::INVALID, PreferenceReadStatus::EXPIRED], true)) {
            return;
        }
        try {
            $this->storage->clear($request, $event->getResponse());
            $request->attributes->set(self::PREFERENCE_CLEARED_ATTRIBUTE, true);
        } catch (PersistenceFailureExceptionInterface $failure) {
            if (PersistenceFailureStrategy::THROW === $this->failureStrategy) {
                throw $failure;
            }
        }
    }
}
