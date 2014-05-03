TimezoneBundle
==============
The TimezoneBundle adds support for guessing a appropiate Timezone serverside in your Symfony 2.1 application.

[![Latest Stable Version](https://poser.pugx.org/lunetics/timezone-bundle/v/stable.png)](https://packagist.org/packages/lunetics/timezone-bundle)
[![Build Status](https://secure.travis-ci.org/lunetics/TimezoneBundle.png?branch=master)](http://travis-ci.org/lunetics/TimezoneBundle)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/lunetics/TimezoneBundle/badges/quality-score.png?s=cd22c2fddc8121153c3827f5321f46d7564ef62d)](https://scrutinizer-ci.com/g/lunetics/TimezoneBundle/)
[![Code Coverage](https://scrutinizer-ci.com/g/lunetics/TimezoneBundle/badges/coverage.png?s=ef7f518cbc1033d923145f37aa4288e01af96302)](https://scrutinizer-ci.com/g/lunetics/TimezoneBundle/)


[![Latest Unstable Version](https://poser.pugx.org/lunetics/timezone-bundle/v/unstable.png)](https://packagist.org/packages/lunetics/timezone-bundle)
[![Total Downloads](https://poser.pugx.org/lunetics/timezone-bundle/downloads.png)](https://packagist.org/packages/lunetics/timezone-bundle)
[![License](https://poser.pugx.org/lunetics/timezone-bundle/license.png)](https://packagist.org/packages/lunetics/timezone-bundle)

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
