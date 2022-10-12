<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use Safe\Exceptions\SafeExceptionInterface;

use function htmlentities;
use function preg_replace_callback;
use function Safe\file_get_contents;
use function sprintf;

use const ENT_QUOTES;

final class InlineFeatureFile implements PageFormatter
{
    public function __construct(private string $featuresPath, private readonly LoggerInterface $logger)
    {
    }

    /** @throws SafeExceptionInterface */
    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Checking if feature files can be inlined in %s', self::class, $page->slug()));

        return $page->withReplacedContent(
            preg_replace_callback(
                '/{{feature:([a-zA-Z0-9\/.-]+)}}/',
                function (array $m) use ($page): string {
                    /** @var array{1: string} $m */
                    $this->logger->debug(sprintf('[%s] Inlining feature file "%s" in page "%s"', self::class, $m[1], $page->slug()));

                    $feature = file_get_contents($this->featuresPath . '/' . $m[1]);

                    return '<pre><code class="lang-gherkin">' . htmlentities($feature, ENT_QUOTES) . '</code></pre>';
                },
                $page->content(),
            ),
        );
    }
}
