Guessers
========

There are currently 3 timezone guessers available:

1. geo
2. locale_mapper
3. locale

geo guesser
-----------
The geo guesser utilizes the **pecl-geoip** extension and [MaxMinds geoip Database](http://www.maxmind.com/)
It tries to locate the client and set the appropiate timezone.

locale_mapper guesser
---------------------
The locale_mapper guesser tries to identify the timezone based on the locale mapping provided in the [LocaleMapper.yml](https://github.com/lunetics/TimezoneBundle/blob/master/Resources/config/LocaleMapper.yml).

locale guesser
--------------
The locale guesser tries to identify the timezone based on the current locale via the **pecl-geoip** extension.

Custom Guesser
==============
If you want to implement your own timezone guesser, you need the following steps:

1. Define the guesser class in the config
-----------------------------------------
Add the following configuration to `app/config/config.yml`:

``` yaml
lunetics_timezone:
    service:
        acme_guesser:
            class: Acme\AcmeBundle\TimezoneGuesser\AcmeTimezoneGuesser
```

2. Add the guesser as service
------------------
It is important that you add exactly this tag name.
``` xml
<service id="lunetics_timezone.acme_guesser" class="%lunetics_timezone.service.acme_guesser.class%">
    <tag name="lunetics_timezone.guesser" alias="acme_guesser" />
</service>
```

3. Build your guesser class
--------------------------
You need to build the guesser class. The class must implement the TimezoneGuesserInterface.

If a timezone was found, the `guessTimezone` function must set the timezone in the object and return the string value of the timezone.
If no timezone was found, the `guessTimezone` function must return
``` php
// src/Acme/AcmeBundle/TimezoneGuesser/AcmeTimezoneGuesser.php
<?php
namespace Acme\AcmeBundle\TimezoneGuesser;

use Symfony\Component\HttpFoundation\Request;
use Lunetics\TimezoneBundle\TimezoneGuesser\TimezoneGuesserInterface;

class AcmeTimezoneGuesser implements TimezoneGuesserInterface
{
    private $identifiedTimezone;

    public function guessTimezone(Request $request)
    {
        // Code to identify the timezone, if found:
        if($foundTimezone) {
            $this->identifiedTimezone = $foundTimezone;
            return $this->identifiedTimezone;
        }

        return false;
    }

    public function getIdentifiedTimezone()
    {
        return $this->identifiedTimezone;
    }
}
```

4. Add the guesser to the config
--------------------------------
Add the Service alias tag name to the order in `app/config/config.yml`:
``` yaml
lunetics_timezone:
    guesser:
        order:
            - acme_guesser
            - geo
            - locale_mapper
            - locale
```
