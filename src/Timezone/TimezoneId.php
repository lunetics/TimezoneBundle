<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Timezone;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;

final readonly class TimezoneId implements \Stringable
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (!isset(self::knownIdentifiers()[$value])) {
            throw InvalidTimezoneException::invalidIdentifier();
        }

        return new self($value);
    }

    public static function fromDateTimeZone(\DateTimeZone $timezone): self
    {
        return self::fromString($timezone->getName());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toDateTimeZone(): \DateTimeZone
    {
        return new \DateTimeZone($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** @return array{value: string} */
    public function __serialize(): array
    {
        return ['value' => $this->value];
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        if (1 !== count($data) || !isset($data['value']) || !is_string($data['value'])) {
            throw InvalidTimezoneException::invalidIdentifier();
        }

        $validated = self::fromString($data['value']);
        $this->value = $validated->value;
    }

    /** @return array<string, true> */
    private static function knownIdentifiers(): array
    {
        /** @var array<string, true>|null $identifiers */
        static $identifiers = null;

        if (null === $identifiers) {
            $identifiers = array_fill_keys(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true);
        }

        return $identifiers;
    }
}
