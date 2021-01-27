<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use Roave\DocbookTool\DocbookPage;

interface OutputWriter
{
    /** @param DocbookPage[] $docbookPages */
    public function __invoke(array $docbookPages): void;
}
