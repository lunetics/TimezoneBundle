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