<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Exception\PersistenceFailureExceptionInterface;
use Lunetics\TimezoneBundle\Resolution\PersistenceFailureStrategy;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\PreferenceSource;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceRead;
use Symfony\Component\HttpFoundation\Request;

final readonly class StoredPreferenceTimezoneResolver implements TimezoneResolverInterface
{
    public const MANUAL_PRIORITY = 925;
    public const BROWSER_PRIORITY = 800;
    public const READ_ATTRIBUTE = '_lunetics_timezone.preference_read';

    public function __construct(
        private TimezonePreferenceStorageInterface $storage,
        private ?PreferenceSource $source = null,
        private PersistenceFailureStrategy $failureStrategy = PersistenceFailureStrategy::THROW,
    )
    {
    }

    public function resolve(Request $request): ?TimezoneResolution
    {
        $cached = $request->attributes->get(self::READ_ATTRIBUTE);
        if ($cached instanceof TimezonePreferenceRead) {
            $read = $cached;
        } else {
            try {
                $read = $this->storage->read($request);
            } catch (PersistenceFailureExceptionInterface $failure) {
                if (PersistenceFailureStrategy::THROW === $this->failureStrategy) {
                    throw $failure;
                }
                return null;
            }
            $request->attributes->set(self::READ_ATTRIBUTE, $read);
        }
        if (PreferenceReadStatus::VALID !== $read->status || null === $read->preference || (null !== $this->source && $read->preference->source !== $this->source)) {
            return null;
        }
        return new TimezoneResolution($read->preference->timezone, 'persisted_'.$read->preference->source->value, ResolutionKind::PERSISTED);
    }
}
