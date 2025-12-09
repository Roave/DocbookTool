<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Override;
use Roave\DocbookTool\DocbookPage;

/** @final */
class AggregatePageFormatter implements PageFormatter
{
    /**
     * @param PageFormatter[] $formatters
     * @psalm-param non-empty-list<PageFormatter> $formatters
     */
    public function __construct(private array $formatters)
    {
    }

    #[Override]
    public function __invoke(DocbookPage $page): DocbookPage
    {
        foreach ($this->formatters as $formatter) {
            $page = $formatter($page);
        }

        return $page;
    }
}
