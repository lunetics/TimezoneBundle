<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Context;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;

interface TimezoneExecutionContextInterface
{
    public function run(TimezoneId $timezone, callable $callback): mixed;
}
