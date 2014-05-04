<?php

namespace Lunetics\TimezoneBundle\TimezoneProvider;

use Faker\Provider\DateTime;
use Lunetics\TimezoneBundle\Exception\TimezoneException;
use Lunetics\TimezoneBundle\Validator\Timezone;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * Class TimezoneProvider
 */
class TimezoneProvider
{
    /**
     * @var string
     */
    protected $timezone;

    /**
     * @var \Symfony\Component\Validator\ValidatorInterface
     */
    protected $validator;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ValidatorInterface $validator
     * @param string             $defaultTimezone
     * @param LoggerInterface    $logger
     */
    public function __construct(ValidatorInterface $validator, $defaultTimezone = 'UTC', LoggerInterface $logger = null)
    {
        $this->validator = $validator;
        $this->logger = $logger ? : new NullLogger();
        $this->timezone = $defaultTimezone;
    }

    /**
     * Sets the timezone
     *
     * @param $timezone
     *
     * @return $this
     * @throws \Lunetics\TimezoneBundle\Exception\TimezoneException
     */
    public function setTimezone($timezone)
    {
        $errors = $this->validator->validateValue($timezone, new Timezone());
        if ($errors->count() > 0) {
            $iterator = $errors->getIterator();
            while ($iterator->valid()) {
                $this->logger->error($iterator->current());
                $iterator->next();
            }

            throw new TimezoneException(sprintf('Trying to set invalid timezone "%s"', $timezone));
        }
        $this->timezone = $timezone;
        $this->logger->info(sprintf('TimezoneProvider: Set timezone to [ %s ]', $this->timezone));

        return $this;
    }

    /**
     * Returns the timezone
     *
     * @return string
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * @return \DateTimeZone
     */
    public function getDateTimezoneObject()
    {
        return new \DateTimeZone($this->timezone);
    }
}