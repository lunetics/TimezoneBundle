<?php

declare(strict_types=1);

use Lunetics\TimezoneBundle\Clock\SystemClock;
use Lunetics\TimezoneBundle\LuneticsTimezoneBundle;
use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\SessionTimezoneStorage;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';

if (!is_file($autoload)) {
    throw new RuntimeException('Missing vendor/autoload.php; install production dependencies first.');
}

require $autoload;

$bundleClass = LuneticsTimezoneBundle::class;
if (!class_exists($bundleClass)) {
    throw new RuntimeException(sprintf('%s is not autoloadable.', $bundleClass));
}

$bundle = new LuneticsTimezoneBundle();
if ($root !== $bundle->getPath()) {
    throw new RuntimeException('Bundle root does not match the package root.');
}

foreach ([
    'Resources/config/routes.php',
    'Resources/public/timezone.js',
    'Resources/views/Collector/timezone.html.twig',
    'Resources/doc/scope.md',
    'Resources/doc/v2-implementation-plan.md',
    'LICENSE',
] as $runtimeFile) {
    if (!is_file($root.'/'.$runtimeFile)) {
        throw new RuntimeException(sprintf('Missing shipped runtime file: %s.', $runtimeFile));
    }
}

foreach ([
    'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle',
    'Symfony\\Component\\Form\\AbstractTypeExtension',
    'Twig\\Environment',
    'GeoIp2\\Database\\Reader',
] as $developmentOnlyClass) {
    if (class_exists($developmentOnlyClass)) {
        throw new RuntimeException(sprintf('Development-only integration is present: %s.', $developmentOnlyClass));
    }
}

$timezone = TimezoneId::fromString('UTC');
$clock = new SystemClock();
$storage = new SessionTimezoneStorage();
if ('UTC' !== $timezone->value() || !$clock->now() instanceof DateTimeImmutable) {
    throw new RuntimeException('Core timezone or clock construction failed.');
}
if (PreferenceReadStatus::ABSENT !== $storage->read(new Request())->status) {
    throw new RuntimeException('A sessionless request must produce an ABSENT preference read.');
}

$container = new ContainerBuilder();
$container->setParameter('kernel.environment', 'prod');
$container->setParameter('kernel.debug', false);
$container->setParameter('kernel.build_dir', sys_get_temp_dir().'/lunetics-timezone-bundle-no-dev/build');
$container->setParameter('kernel.cache_dir', sys_get_temp_dir().'/lunetics-timezone-bundle-no-dev/cache');
$container->setParameter('kernel.project_dir', $root);
$extension = $bundle->getContainerExtension();
if (null === $extension) {
    throw new RuntimeException('Bundle container extension is unavailable.');
}
$container->registerExtension($extension);
$container->loadFromExtension($extension->getAlias(), ['integrations' => ['form' => true]]);
$bundle->build($container);

try {
    $container->compile();
} catch (LogicException $failure) {
    if (!str_contains($failure->getMessage(), 'symfony/form')) {
        throw new RuntimeException('Missing form dependency failed without mentioning symfony/form.', 0, $failure);
    }

    fwrite(STDOUT, "No-dev smoke passed.\n");
    exit(0);
}

throw new RuntimeException('Enabling form integration without symfony/form did not fail.');
