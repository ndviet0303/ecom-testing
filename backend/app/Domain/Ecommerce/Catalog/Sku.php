<?php

namespace App\Domain\Ecommerce\Catalog;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

final class Sku
{
    public const MAX_LENGTH = 64;

    private function __construct(
        public readonly string $value
    ) {}

    public static function fromString(string $raw): self
    {
        $value = trim($raw);

        if ($value === '') {
            throw new InvalidDomainArgumentException('SKU cannot be empty.');
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainArgumentException('SKU exceeds maximum length.');
        }

        return new self($value);
    }
}
