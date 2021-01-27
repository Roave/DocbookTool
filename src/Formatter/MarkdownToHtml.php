<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Michelf\MarkdownExtra;
use Roave\DocbookTool\DocbookPage;

final class MarkdownToHtml implements PageFormatter
{
    private MarkdownExtra $markdownParser;

    public function __construct()
    {
        $this->markdownParser = new MarkdownExtra();
        $this->markdownParser->code_class_prefix = 'lang-';
    }

    public function __invoke(DocbookPage $page) : DocbookPage
    {
        return $page->withReplacedContent(
            $this->markdownParser->transform($page->content())
        );
    }
}
