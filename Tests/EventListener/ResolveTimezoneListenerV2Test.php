<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\EventListener;

use Lunetics\TimezoneBundle\Context\CurrentTimezoneProvider;
use Lunetics\TimezoneBundle\Event\TimezoneResolvedEvent;
use Lunetics\TimezoneBundle\EventListener\ResolveTimezoneListener;
use Lunetics\TimezoneBundle\Resolution\ResolutionFailureStrategy;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolutionTrace;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolverChain;
use Lunetics\TimezoneBundle\Resolver\TimezoneResolverInterface;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class ResolveTimezoneListenerV2Test extends TestCase
{
    public function testFixedRequestPriority(): void
    {
        self::assertSame([KernelEvents::REQUEST => ['onKernelRequest', 1]], ResolveTimezoneListener::getSubscribedEvents());
    }

    public function testMainRequestResolvesAttachesDiagnosticsAndDispatchesOnce(): void
    {
        [$listener, $resolver, $dispatcher] = $this->listener();
        $request = Request::create('/');

        $listener->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        self::assertSame(1, $resolver->calls);
        $resolution = $request->attributes->get(CurrentTimezoneProvider::RESOLUTION_ATTRIBUTE);
        self::assertInstanceOf(TimezoneResolution::class, $resolution);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertInstanceOf(TimezoneResolutionTrace::class, TimezoneResolutionTrace::fromRequest($request));
        self::assertCount(1, $dispatcher->events);
        self::assertInstanceOf(TimezoneResolvedEvent::class, $dispatcher->events[0]);
        self::assertSame($resolution, $dispatcher->events[0]->resolution);
    }

    public function testSubRequestDoesNotResolveAttachDiagnosticsOrDispatch(): void
    {
        [$listener, $resolver, $dispatcher] = $this->listener();
        $request = Request::create('/');

        $listener->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST));

        self::assertSame(0, $resolver->calls);
        self::assertFalse($request->attributes->has(CurrentTimezoneProvider::RESOLUTION_ATTRIBUTE));
        self::assertNull(TimezoneResolutionTrace::fromRequest($request));
        self::assertSame([], $dispatcher->events);
    }

    /** @return array{ResolveTimezoneListener, RecordingTimezoneResolver, RecordingEventDispatcher} */
    private function listener(): array
    {
        $resolver = new RecordingTimezoneResolver();
        $chain = new TimezoneResolverChain(['recording' => $resolver], TimezoneId::fromString('UTC'), ResolutionFailureStrategy::CONTINUE);
        $dispatcher = new RecordingEventDispatcher();

        return [new ResolveTimezoneListener($chain, $dispatcher), $resolver, $dispatcher];
    }
}

final class RecordingTimezoneResolver implements TimezoneResolverInterface
{
    public int $calls = 0;

    public function resolve(Request $request): TimezoneResolution
    {
        ++$this->calls;

        return new TimezoneResolution(TimezoneId::fromString('Europe/Berlin'), 'recording', ResolutionKind::EXPLICIT);
    }
}

final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        return $event;
    }
}
