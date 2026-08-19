<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Exception;

final class TimezoneStorageException extends TimezoneException implements PersistenceFailureExceptionInterface
{
    public static function operationFailed(string $operation, \Throwable $previous): self
    {
        return new self(sprintf('Timezone preference storage %s failed.', $operation), 0, $previous);
    }
}
