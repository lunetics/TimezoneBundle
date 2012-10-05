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

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Validator\Validator;
use Symfony\Component\HttpFoundation\Response;

use Lunetics\TimezoneBundle\TimezoneGuesser\TimezoneGuesserManager;

/**
 * Listener for Timezone detection
 *
 * @author Matthias Breddin <mb@lunetics.com>
 */
class TimezoneListener
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
     * @param Validator              $validator Timzone Validator
     * @param LoggerInterface        $logger    Logger
     */
    public function __construct(Session $session, $sessionVar, TimezoneGuesserManager $manager, Validator $validator, LoggerInterface $logger = null)
    {
        $this->session = $session;
        $this->manager = $manager;
        $this->validator = $validator;
        $this->logger = $logger;
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
            $this->logEvent('Request is not a "MASTER_REQUEST" : SKIPPING...');

            return;
        }

        if (!$this->session->has($this->sessionTimezoneString)) {
            $this->timezone = $this->manager->runTimezoneGuessing($request);
            $errors = $this->validator->validateValue($this->timezone, new \Lunetics\TimezoneBundle\Validator\Timezone());
            if (count($errors) > 0) {
                if (null !== $this->logger) {
                    $this->logger->notice(sprintf('Timezone %s is Invalid!', $this->timezone));
                }

                return;
            }
            $this->logEvent(sprintf('Setting [ %s ] as default timezone into session var [ %s ]', $this->timezone, $this->sessionTimezoneString));
            $this->session->set($this->sessionTimezoneString, $this->timezone);
        }
    }

    /**
     * Log detection events
     *
     * @param string $logMessage
     * @param array  $parameters
     */
    private function logEvent($logMessage, $parameters = null)
    {
        if (null !== $this->logger) {
            $this->logger->info(sprintf($logMessage, $parameters));
        }
    }
}