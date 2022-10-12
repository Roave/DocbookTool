<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use Psr\Log\LoggerInterface;

use function sprintf;
use function usort;

class SortThePages
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param DocbookPage[] $unsortedPages
     *
     * @return DocbookPage[]
     */
    public function __invoke(array $unsortedPages): array
    {
        $this->logger->debug(sprintf('[%s] Sorting pages by slug', self::class));

        $pages = $unsortedPages;
        usort(
            $pages,
            static function (DocbookPage $a, DocbookPage $b): int {
                // Sort by order (can be overridden with `order:` front matter), then alphabetically
                return $a->order() <=> $b->order()
                    ?: ($a->slug() <=> $b->slug());
            },
        );

        return $pages;
    }
}
