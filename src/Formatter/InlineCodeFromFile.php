<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Psl\Regex;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\RetrieveFileContents;

use function htmlentities;
use function implode;
use function sprintf;

use const ENT_QUOTES;

final class InlineCodeFromFile implements PageFormatter
{
    private const array ALLOWED_CODE_TYPES = ['json'];

    public function __construct(private string $contentPath, private readonly LoggerInterface $logger, private readonly RetrieveFileContents $retrieveFileContents)
    {
    }

    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Checking if source code files can be inlined in %s', self::class, $page->slug()));

        return $page->withReplacedContent(
            Regex\replace_with(
                $page->content(),
                sprintf(
                    '/{{src-(%s):([a-zA-Z0-9\/.-]+)}}/',
                    implode('|', self::ALLOWED_CODE_TYPES),
                ),
                function (array $m) use ($page): string {
                    /** @var array{1: string, 2: non-empty-string} $m */
                    $this->logger->debug(sprintf('[%s] Inlining source code file "%s" of type "%s" in page "%s"', self::class, $m[2], $m[1], $page->slug()));

                    $sourceCode = ($this->retrieveFileContents)($m[2], $this->contentPath);

                    return sprintf(
                        '<pre><code class="lang-%s">%s</code></pre>',
                        htmlentities($m[1], ENT_QUOTES),
                        htmlentities($sourceCode, ENT_QUOTES),
                    );
                },
            ),
        );
    }
}
