<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

final class TestLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<string> */
    public array $logMessages = [];

    /** @param array<array-key,mixed> $context */
    public function log(mixed $level, Stringable|string $message, array $context = []): void
    {
        $this->logMessages[] = (string) $message;
    }
}
