<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\EventListener;

use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Validator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use Lunetics\TimezoneBundle\TimezoneGuesser\TimezoneGuesserManager;
use Lunetics\TimezoneBundle\Event\FilterTimezoneEvent;
use Lunetics\TimezoneBundle\TimezoneBundleEvents;
use Lunetics\TimezoneBundle\Validator\Timezone;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * Listener for Timezone detection
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class TimezoneListener implements EventSubscriberInterface
{
    protected $session;
    protected $manager;
    protected $validator;
    protected $logger;
    protected $timezone;
    protected $sessionTimezoneString;

    /**
     * Construct the TimezoneListener
     *
     * @param Session                $session   Session
     * @param string                 $sessionVar
     * @param TimezoneGuesserManager $manager   The Timezone Manager
     * @param ValidatorInterace      $validator Timzone Validator
     * @param LoggerInterface        $logger    Logger
     */
    public function __construct(Session $session, $sessionVar, TimezoneGuesserManager $manager, ValidatorInterface $validator, LoggerInterface $logger = null)
    {
        $this->session = $session;
        $this->manager = $manager;
        $this->validator = $validator;
        $this->logger = $logger ? new NullLogger();
        $this->sessionTimezoneString = $sessionVar;
    }

    /**
     * Called at the "kernel.request" event
     *
     * Call the TimezoneGuesserManager to guess the Timezone by the activated guessers
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST  && !$request->isXmlHttpRequest()) {
            $this->logger->info('Request is not a "MASTER_REQUEST" : SKIPPING...');

            return;
        }

        if (!$this->session->has($this->sessionTimezoneString)) {
            $this->timezone = $this->manager->runTimezoneGuessing($request);

            $errors = $this->validator->validateValue($this->timezone, new Timezone());

            if ($errors->count() > 0) {
                $iterator = $errors->getIterator();
                while ($iterator->valid()) {
                    $this->logger->notice($iterator->current());
                    $iterator->next();
                }

                return;
            }

            $localeSwitchEvent = new FilterTimezoneEvent($this->timezone);
            $event->getDispatcher()->dispatch(TimezoneBundleEvents::TIMEZONE_CHANGE, $localeSwitchEvent);
        }
    }

    /**
     * Sets the timezone in the session
     *
     * @param FilterTimezoneEvent $event
     */
    public function onTimezoneChange(FilterTimezoneEvent $event)
    {
        $timezone = $event->getTimezone();
        $this->session->set($this->sessionTimezoneString, $timezone);
        $this->logger->info(sprintf('Setting [ %s ] as default timezone into session var [ %s ]', $timezone, $this->sessionTimezoneString));
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
             KernelEvents::REQUEST => array('onKernelRequest'),
             TimezoneBundleEvents::TIMEZONE_CHANGE => array('onTimezoneChange')
        );
    }
}