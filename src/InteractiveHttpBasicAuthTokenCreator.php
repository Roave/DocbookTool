<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

class InteractiveHttpBasicAuthTokenCreator
{
    public static function isInteractiveTty(): bool
    {
        return extension_loaded('posix') && posix_isatty(STDIN);
    }

    private function readInput(bool $secretive): string
    {
        /** @psalm-suppress ForbiddenCode */
        $input = shell_exec(sprintf("bash -c 'read %s input && echo \$input'", $secretive ? '-s' : ''));
        assert(is_string($input));

        return rtrim($input);
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
