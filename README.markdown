TimezoneBundle
==============
The TimezoneBundle adds support for guessing a appropiate Timezone serverside in your Symfony 2.1 application.

[![Build Status](https://secure.travis-ci.org/lunetics/TimezoneBundle.png?branch=master)](http://travis-ci.org/lunetics/TimezoneBundle)

About
-----
This Bundle offers Timezone detection via a Kernel-listener.
Also included is a TimezoneValidator.

Included are 3 **TimezoneGuessers**

* geoip
* locale_mapper
* locale

You can define the order of which guesser should be called first.
Once a guesser finds a appropiate timezone, the timezone will be stored in the session variable **lunetics_timezone**.

Requirements
------------
To use the **geoip** guesser and the **locale** guesser, the **pecl-geoip** extension must be installed and working.

Documentation
-------------
[Read the Documentation](https://github.com/lunetics/TimezoneBundle/blob/master/Resources/doc/index.md)

Installation
------------
[Read the Documentation](https://github.com/lunetics/TimezoneBundle/blob/master/Resources/doc/installation.md)

License
-------
This bundle is under the MIT license.

Authors
-------
Matthias Breddin : [@lunetics](https://github.com/lunetics)  
