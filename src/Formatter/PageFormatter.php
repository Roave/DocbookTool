<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Roave\DocbookTool\DocbookPage;

interface PageFormatter
{
    public function __invoke(DocbookPage $page): DocbookPage;
}
