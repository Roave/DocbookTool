<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Roave\DocbookTool\DocbookPage;

final class ExtractFrontMatter implements PageFormatter
{
    public function __invoke(DocbookPage $page): DocbookPage
    {
        // @todo extract YAML front matter here and store in the page object
        return $page;
    }
}
