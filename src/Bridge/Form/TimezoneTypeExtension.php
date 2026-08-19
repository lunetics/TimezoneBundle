<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Bridge\Form;

use Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TimezoneTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly CurrentTimezoneProviderInterface $provider)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [DateTimeType::class, DateType::class, TimeType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('view_timezone', function (Options $options): string {
            $timezone = $this->provider->getTimezone()->value();
            // Symfony rejects differing model and view timezones unless a
            // reference date resolves the offset. An explicitly configured
            // model timezone therefore wins over the current one, so enabling
            // this bridge never breaks a form that configures its own model
            // timezone.
            // `isset()` on Options only calls offsetExists(), which is true for
            // a defined-but-null option — the value has to be read instead.
            $model = $options['model_timezone'] ?? null;
            if (is_string($model) && $model !== $timezone && null === ($options['reference_date'] ?? null)) {
                return $model;
            }

            return $timezone;
        });
    }
}
