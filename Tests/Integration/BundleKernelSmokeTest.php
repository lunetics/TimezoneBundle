<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Integration;

use DateTimeImmutable;
use Lunetics\TimezoneBundle\Bridge\Messenger\DispatchTimezoneMiddleware;
use Lunetics\TimezoneBundle\Bridge\Messenger\WorkerTimezoneMiddleware;
use Lunetics\TimezoneBundle\Bridge\WebProfiler\TimezoneDataCollector;
use Lunetics\TimezoneBundle\Clock\SystemClock;
use Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface;
use Lunetics\TimezoneBundle\DependencyInjection\Compiler\TimezoneCompilerPass;
use Lunetics\TimezoneBundle\LuneticsTimezoneBundle;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolverChain;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolutionTrace;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

final class BundleKernelSmokeTest extends TestCase
{
    private ?BundleSmokeKernel $kernel = null;
    private ?string $runtimeDirectory = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        restore_exception_handler();
        if (null !== $this->runtimeDirectory && is_dir($this->runtimeDirectory)) {
            $this->removeDirectory($this->runtimeDirectory);
        }
        $this->kernel = null;
        $this->runtimeDirectory = null;
    }

    public function testBundleWorksInARealDebugKernel(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir().'/lunetics_timezone_kernel_'.bin2hex(random_bytes(8));
        $this->kernel = new BundleSmokeKernel('test', true, dirname(__DIR__, 2), $this->runtimeDirectory);
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        $provider = $container->get(CurrentTimezoneProviderInterface::class);
        self::assertInstanceOf(CurrentTimezoneProviderInterface::class, $provider);
        self::assertSame('UTC', (string) $provider->getTimezone());
        self::assertSame('default', $provider->getResolution()->source);

        $request = new Request();
        $resolverChain = $container->get('test.timezone_resolver_chain');
        self::assertInstanceOf(TimezoneResolverChain::class, $resolverChain);
        $resolverChain->resolve($request);
        $trace = TimezoneResolutionTrace::fromRequest($request);
        self::assertNotNull($trace);
        self::assertSame([
            'request_attribute',
            'stored_manual',
            'user',
            'stored_browser',
            'locale',
            'configured_default',
        ], array_column($trace->attempts, 'resolver'));

        self::assertInstanceOf(DispatchTimezoneMiddleware::class, $container->get('lunetics_timezone.messenger.dispatch_middleware'));
        self::assertInstanceOf(WorkerTimezoneMiddleware::class, $container->get('lunetics_timezone.messenger.worker_middleware'));

        $clock = $container->get('test.clock');
        self::assertNotInstanceOf(SystemClock::class, $clock);
        self::assertInstanceOf(BundleSmokeApplicationClock::class, $clock);
        self::assertSame($clock, $container->get('test.timezone_clock'));

        $router = $container->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        $router->getContext()->setMethod('POST');
        $route = $router->match('/_lunetics/timezone/browser');
        self::assertSame('lunetics_timezone_browser', $route['_route']);

        $browserRequest = Request::create(
            '/_lunetics/timezone/browser',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"timezone":"Europe/Berlin"}',
        );
        $browserResponse = $this->kernel->handle($browserRequest);
        self::assertSame(403, $browserResponse->getStatusCode());
        $this->kernel->terminate($browserRequest, $browserResponse);

        $session = new Session(new MockArraySessionStorage());
        $csrfRequest = new Request();
        $csrfRequest->setSession($session);
        $requestStack = $container->get('test.request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($csrfRequest);
        $csrfTokenManager = $container->get('test.csrf_token_manager');
        self::assertInstanceOf(CsrfTokenManagerInterface::class, $csrfTokenManager);
        $csrfToken = $csrfTokenManager->getToken('lunetics_timezone.preference')->getValue();
        self::assertSame($csrfRequest, $requestStack->pop());

        $browserRequest = Request::create(
            '/_lunetics/timezone/browser',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrfToken],
            '{"timezone":"Europe/Berlin"}',
        );
        $browserRequest->setSession($session);
        $browserResponse = $this->kernel->handle($browserRequest);
        self::assertSame(204, $browserResponse->getStatusCode());
        $this->kernel->terminate($browserRequest, $browserResponse);

        $profiler = $container->get('profiler');
        self::assertInstanceOf(Profiler::class, $profiler);
        self::assertTrue($profiler->has('lunetics_timezone'));
        $debugToken = $browserResponse->headers->get('X-Debug-Token');
        self::assertNotNull($debugToken);
        $profile = $profiler->loadProfile($debugToken);
        self::assertNotNull($profile);
        $collector = $profile->getCollector('lunetics_timezone');
        self::assertInstanceOf(TimezoneDataCollector::class, $collector);
        $diagnostics = $collector->getDiagnostics();
        self::assertSame('UTC', $diagnostics['effective_timezone']);
        self::assertSame('default', $diagnostics['effective_source']);
        self::assertTrue($diagnostics['preference_written']);

        $twig = $container->get('test.twig');
        self::assertInstanceOf(Environment::class, $twig);
        $template = $twig->load('@LuneticsTimezone/Collector/timezone.html.twig');
        self::assertNotSame('', $template->getSourceContext()->getCode());

        $assetMapper = $container->get('test.asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $assetMapper);
        self::assertNotNull($assetMapper->getAsset('bundles/luneticstimezone/timezone.js'));
    }

    public function testBrowserPersistenceFailureWithoutFrameworkSessionsReturnsServiceUnavailable(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir().'/lunetics_timezone_kernel_'.bin2hex(random_bytes(8));
        $this->kernel = new BundleSmokeKernel('test', true, dirname(__DIR__, 2), $this->runtimeDirectory, false, false);
        $this->kernel->boot();

        $browserRequest = Request::create(
            '/_lunetics/timezone/browser',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"timezone":"Europe/Berlin"}',
        );
        $browserResponse = $this->kernel->handle($browserRequest);
        self::assertSame(503, $browserResponse->getStatusCode());
        $this->kernel->terminate($browserRequest, $browserResponse);
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);
        if (false === $items) {
            return;
        }
        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $directory.'/'.$item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}

final class BundleSmokeApplicationClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2024-01-01T00:00:00+00:00');
    }
}

final class BundleSmokeKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $projectDirectory,
        private readonly string $runtimeDirectory,
        private readonly bool $frameworkSessionsEnabled = true,
        private readonly bool $browserCsrfEnabled = true,
    ) {
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->projectDirectory;
    }

    public function getCacheDir(): string
    {
        return $this->runtimeDirectory.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->runtimeDirectory.'/log';
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new WebProfilerBundle();
        yield new LuneticsTimezoneBundle();
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $frameworkConfig = [
            'secret' => 'bundle-smoke-test-secret',
            'test' => true,
            'csrf_protection' => $this->browserCsrfEnabled,
            'form' => true,
            'router' => ['utf8' => true],
            'profiler' => ['enabled' => true, 'collect' => true],
            'asset_mapper' => ['enabled' => true],
        ];
        if ($this->frameworkSessionsEnabled) {
            $frameworkConfig['session'] = ['storage_factory_id' => 'session.storage.factory.mock_file'];
        }
        $container->extension('framework', $frameworkConfig);
        $container->extension('security', [
            'providers' => ['users' => ['memory' => null]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users']],
        ]);
        $container->extension('twig', ['default_path' => $this->projectDirectory.'/Resources/views']);
        $container->extension('web_profiler', ['toolbar' => false, 'intercept_redirects' => false]);
        $container->extension('lunetics_timezone', [
            'browser' => ['enabled' => true, 'csrf' => ['enabled' => $this->browserCsrfEnabled]],
            'integrations' => ['twig' => true, 'form' => true, 'messenger' => true, 'profiler' => true],
        ]);
        $services = $container->services();
        $services->set(BundleSmokeApplicationClock::class);
        $services->alias(ClockInterface::class, BundleSmokeApplicationClock::class);
        $services->alias('test.twig', 'twig')->public();
        $services->alias('test.asset_mapper', 'asset_mapper')->public();
        $services->alias('test.timezone_resolver_chain', TimezoneResolverChain::class)->public();
        $services->alias('test.clock', ClockInterface::class)->public();
        $services->alias('test.timezone_clock', TimezoneCompilerPass::CLOCK_SERVICE)->public();
        $services->alias('test.request_stack', 'request_stack')->public();
        if ($this->browserCsrfEnabled) {
            $services->alias('test.csrf_token_manager', 'security.csrf.token_manager')->public();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import($this->projectDirectory.'/Resources/config/routes.php');
    }
}
