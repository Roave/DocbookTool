<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use Roave\DocbookTool\DocbookPage;

final class ConfluenceWriter implements OutputWriter
{
    /** @param DocbookPage[] $docbookPages */
    public function __invoke(array $docbookPages): void
    {
        // @todo write to a confluence instance
    }
}
