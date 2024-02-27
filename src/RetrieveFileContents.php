<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

interface RetrieveFileContents
{
    /** @param non-empty-string $filePath */
    public function __invoke(string $filePath, string $workingDirectory): string;
}
