<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use Roave\DocbookTool\Formatter\PageFormatter;

class FormatAllThePages
{
    /** @param PageFormatter[] $formatters */
    public function __construct(private array $formatters)
    {
    }

    /**
     * @param DocbookPage[] $pages
     *
     * @return DocbookPage[]
     */
    public function __invoke(array $pages): array
    {
        $newPages = [];

        foreach ($pages as $p) {
            foreach ($this->formatters as $formatter) {
                $p = $formatter->__invoke($p);
            }

            $newPages[] = $p;
        }

        return $newPages;
    }
}
