<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Michelf\MarkdownExtra;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;

use function sprintf;

final class MarkdownToHtml implements PageFormatter
{
    private MarkdownExtra $markdownParser;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->markdownParser                    = new MarkdownExtra();
        $this->markdownParser->code_class_prefix = 'lang-';
    }

    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Converting MD to HTML in "%s"', self::class, $page->slug()));

        return $page->withReplacedContent(
            $this->markdownParser->transform($page->content()),
        );
    }
}
