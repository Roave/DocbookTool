<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use RuntimeException;
use Safe\Exceptions\SafeExceptionInterface;

use function getenv;
use function in_array;
use function sprintf;

class Environment
{
    /** @throws SafeExceptionInterface */
    public static function require(string $name): string
    {
        $value = self::optional($name);

        if ($value === null) {
            throw new RuntimeException(sprintf('Environment variable %s must be defined, but was not.', $name));
        }

        return $value;
    }

    public static function optional(string $name): string|null
    {
        return getenv($name) ?: null;
    }

    /** @param list<string> $trueValues */
    public static function optionalBoolean(string $name, array $trueValues = ['yes']): bool
    {
        $value = self::optional($name);

        return in_array($value, $trueValues, true);
    }
}
