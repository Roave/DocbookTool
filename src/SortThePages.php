<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use function usort;

class SortThePages
{
    /**
     * @param DocbookPage[] $unsortedPages
     *
     * @return DocbookPage[]
     */
    public function __invoke(array $unsortedPages): array
    {
        $pages = $unsortedPages;
        usort(
            $pages,
            static function (DocbookPage $a, DocbookPage $b): int {
                // Sort by order (can be overridden with `order:` front matter), then alphabetically
                return $a->order() <=> $b->order()
                    ?: $a->slug() <=> $b->slug();
            },
        );

        return $pages;
    }
}
