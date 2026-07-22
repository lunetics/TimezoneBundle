<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Bridge\Twig;

use Lunetics\TimezoneBundle\Bridge\Twig\TwigTimezoneScope;
use Lunetics\TimezoneBundle\Bridge\Twig\TwigTimezoneSubscriber;
use Lunetics\TimezoneBundle\Event\TimezoneResolvedEvent;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Extension\CoreExtension;

final class TwigTimezoneSubscriberTest extends TestCase
{
    public function testMainAndSubrequestLifecycleAndConsecutiveRequests(): void
    {
        $core = new CoreExtension();
        $core->setTimezone($original = new \DateTimeZone('UTC'));
        $scope = new TwigTimezoneScope($core);
        $subscriber = new TwigTimezoneSubscriber($scope);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $main = new Request();
        $sub = new Request();

        $subscriber->onTimezoneResolved($this->resolved($main, 'Europe/Berlin'));
        self::assertSame('Europe/Berlin', $core->getTimezone()->getName());
        $subscriber->onKernelFinishRequest(new FinishRequestEvent($kernel, $sub, HttpKernelInterface::SUB_REQUEST));
        self::assertSame('Europe/Berlin', $core->getTimezone()->getName());
        $subscriber->onKernelFinishRequest(new FinishRequestEvent($kernel, $main, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame($original, $core->getTimezone());

        $second = new Request();
        $subscriber->onTimezoneResolved($this->resolved($second, 'Asia/Tokyo'));
        self::assertSame('Asia/Tokyo', $core->getTimezone()->getName());
        $subscriber->onKernelTerminate(new TerminateEvent($kernel, $second, new Response()));
        self::assertSame($original, $core->getTimezone());
    }

    private function resolved(Request $request, string $timezone): TimezoneResolvedEvent
    {
        return new TimezoneResolvedEvent(
            $request,
            new TimezoneResolution(TimezoneId::fromString($timezone), 'test', ResolutionKind::EXPLICIT),
        );
    }
}
