<?php

declare(strict_types=1);

namespace Omnipay\IDBank\Api;

use Omnipay\Common\Exception\InvalidRequestException;

final class Operations
{
    public static function all(): array
    {
        return array_merge(CardOperations::all(), BindingOperations::all(), RecurringOperations::all(), ReportingOperations::all(), P2pOperations::all());
    }

    public static function get(string $name): array
    {
        return self::all()[$name] ?? throw new InvalidRequestException('Unsupported IDBank API operation.');
    }
}
