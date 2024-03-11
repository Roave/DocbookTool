<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Michelf\MarkdownExtra;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;

use function ini_set;
use function sprintf;

final class MarkdownToHtml implements PageFormatter
{
    private MarkdownExtra $markdownParser;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->markdownParser                    = new MarkdownExtra();
        $this->markdownParser->code_class_prefix = 'lang-';

        // The PCRE backtrack_limit is increased to support bigger inline content, e.g. images
        // See https://github.com/michelf/php-markdown/issues/399 and https://github.com/michelf/php-markdown/issues/399
        // 5_000_000 is 5-times the default and should allow images up to at least 1 MB
        ini_set('pcre.backtrack_limit', 5_000_000);
    }

    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Converting MD to HTML in "%s"', self::class, $page->slug()));

        return $page->withReplacedContent(
            $this->markdownParser->transform($page->content()),
        );
    }
}
