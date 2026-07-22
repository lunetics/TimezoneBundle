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
        $resolver->setDefault('view_timezone', fn (Options $options): string => $this->provider->getTimezone()->value());
    }
}
