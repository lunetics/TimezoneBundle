Installation
============
This section shows you how to install this bundle

Add the package to your dependencies
------------------------------------
``` php
"require": {
    "lunetics/timezone-bundle": "2.1.*",
    ....
},
```

If you want the bleeding-edge version, add this instead of above:
``` php
"require": {
    "lunetics/timezone-bundle": "dev-master",
    ....
},
```


Register the bundle in your kernel
----------------------------------
``` php
public function registerBundles()
    {
        $bundles = array(
            // ...
            new Lunetics\TimezoneBundle\LuneticsTimezoneBundle(),
        );
```

Update your packages
--------------------
``` sh
php composer.phar update lunetics/timezone-bundle
```

Configuration
=============
Add the following lines to your app/config/config.yml

``` yaml
lunetics_timezone:
    guesser:
        order:
            - geo
            - locale_mapper
            - locale
```

PHP geoip extension
===================
The easiest way to install the pecl `geo` extension is to run:
``` sh
pecl install geoip
```

There shoult be a meta-package in your distributions, e.g. `pecl-geoip` port in FreeBSD or `php5-geoip` in Ubuntu.

Install additional geoip database
---------------------------------
You also need to install the GeoLiteCity.dat file to use the `locale` guesser.
* Download http://geolite.maxmind.com/download/geoip/database/GeoLiteCity.dat.gz (or from Downloads in this github repo)
* gunzip the file
* Rename the file to **GeoIPCity.dat** and move it to `/usr/share/GeoIP/`
