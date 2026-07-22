<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Contract\User;

use Lunetics\TimezoneBundle\Timezone\TimezoneId;

interface UserTimezoneAccessorInterface
{
    public function getTimezoneForUser(object $user): TimezoneId|string|null;
}
