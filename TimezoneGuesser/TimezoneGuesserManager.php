<?php
/**
 * This file is part of the LuneticsTimezoneBundle package.
 *
 * <https://github.com/lunetics/TimezoneBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that is distributed with this source code.
 */

namespace Lunetics\TimezoneBundle\TimezoneGuesser;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

use Lunetics\TimezoneBundle\TimezoneGuesser\TimezoneGuesserInterface;

/**
 * Manager to utilize the Timezone guessing services
 *
 * @author Christophe Willemsen <willemsen.christophe@gmail.com>
 * @author Matthias Breddin <mb@lunetics.com>
 */
class TimezoneGuesserManager
{
    private $guessers;

    private $logger;

    /**
     * Constructor
     *
     * @param array           $order  Order parameter from config
     * @param LoggerInterface $logger Logger
     */
    public function __construct(array $order, LoggerInterface $logger = null)
    {
        $this->order = $order;
        $this->logger = $logger;
    }

    /**
     * Adds a Guesser to the Manager
     *
     * @param TimezoneGuesserInterface $guesser The Guesser Service
     * @param string                   $alias   Alias of the Service
     */
    public function addGuesser(TimezoneGuesserInterface $guesser, $alias)
    {
        $this->guessers[$alias] = $guesser;
    }

    /**
     * Returns the guesser
     *
     * @param string $alias
     *
     * @return mixed
     */
    public function getGuesser($alias)
    {
        if (array_key_exists($alias, $this->guessers)) {
            return $this->guessers[$alias];
        } else {
            return null;
        }
    }

    /**
     * Loops through all the activated Timzone Guessers and
     * calls the guessTimezone methode and passing the current request
     *
     * @param Request $request
     *
     * @throws \InvalidArgumentException
     *
     * @return bool false if no timezone is identified
     * @return bool the timezone identified by the guessers
     */
    public function runTimezoneGuessing(Request $request)
    {
        foreach ($this->order as $guesser) {
            if (null === $this->getGuesser($guesser)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" does not exist.', $guesser));
            }
            $this->logEvent('Timezone %s Guessing Service Loaded', ucfirst($guesser));
            $guesserService = $this->getGuesser($guesser);

            $guessed = false;
            try {
                $guessed = $guesserService->guessTimezone($request);
            } catch (\Exception $e) {
                // Some guessers like the GeoTimezoneGuesser may throw an exception. Log the problem without crashing.
                $this->logEvent($e->getMessage());
            }
            if (false !== $guessed) {
                $timezone = $guesserService->getIdentifiedTimezone();
                $this->logEvent('Timezone has been identified : ( %s )', $timezone);

                return $timezone;
            }
            $this->logEvent('Timezone has not been identified by the %s Guessing Service', ucfirst($guesser));
        }

        return false;
    }

    /**
     * Log detection events
     *
     * @param string $logMessage
     * @param string $parameters
     */
    private function logEvent($logMessage, $parameters = null)
    {
        if (null !== $this->logger) {
            $this->logger->info(sprintf($logMessage, $parameters));
        }
    }
}
