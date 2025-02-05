<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use Psl\Type;

use function base64_encode;
use function extension_loaded;
use function posix_isatty;
use function rtrim;
use function shell_exec;
use function sprintf;

use const STDIN;

class InteractiveHttpBasicAuthTokenCreator
{
    public static function isInteractiveTty(): bool
    {
        return extension_loaded('posix') && posix_isatty(STDIN);
    }

    private function readInput(bool $secretive): string
    {
        /** @psalm-suppress ForbiddenCode */
        return rtrim(
            Type\string()
                ->coerce(shell_exec(sprintf("bash -c 'read %s input && echo \$input'", $secretive ? '-s' : ''))),
        );
    }

    public function __invoke(): string
    {
        echo 'Confluence username: ';
        $confluenceUsername = $this->readInput(secretive: false);

        echo 'Confluence password: ';
        $confluencePassword = $this->readInput(secretive: true);
        echo "\n";

        return 'Basic ' . base64_encode(sprintf('%s:%s', $confluenceUsername, $confluencePassword));
    }
}
