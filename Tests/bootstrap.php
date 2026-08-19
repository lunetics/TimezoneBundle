<?php

declare(strict_types=1);

$autoload = __DIR__.'/../vendor/autoload.php';
if (!is_file($autoload)) {
    throw new LogicException('Run "composer install" before executing the tests.');
}

require $autoload;
