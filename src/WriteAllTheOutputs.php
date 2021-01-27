<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use Roave\DocbookTool\Writer\OutputWriter;

class WriteAllTheOutputs
{
    /** @param OutputWriter[] $writers */
    public function __construct(private array $writers)
    {
    }

    /**
     * @param DocbookPage[] $pages
     */
    public function __invoke(array $pages): void
    {
        foreach ($this->writers as $writer) {
            $writer->__invoke($pages);
        }
    }
}
