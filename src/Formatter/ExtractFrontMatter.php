<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use Safe\Exceptions\SafeExceptionInterface;
use Symfony\Component\Yaml\Yaml;
use Webmozart\Assert\Assert;

use function Safe\preg_match;
use function sprintf;
use function str_contains;

final class ExtractFrontMatter implements PageFormatter
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws SafeExceptionInterface
     * @throws InvalidArgumentException
     */
    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Checking page "%s" for YAML front matter', self::class, $page->slug()));

        if (! str_contains($page->content(), '---')) {
            $this->logger->debug(sprintf('[%s] Page "%s" does not have any front matter', self::class, $page->slug()));

            return $page;
        }

        if (! preg_match('/^---\n([\w\W]+?)\n---\n([\w\W]*)$/', $page->content(), $m)) {
            $this->logger->debug(sprintf('[%s] Page "%s" front matter does not appear correctly formatted, ignoring it', self::class, $page->slug()));

            return $page;
        }

        Assert::notNull($m);
        Assert::count($m, 3);

        $frontMatter = Yaml::parse($m[1]);

        Assert::isArray($frontMatter);

        $this->logger->debug(sprintf('[%s] Successfully extracted front matter from page "%s"', self::class, $page->slug()));

        return $page
            ->withFrontMatter($frontMatter)
            ->withReplacedContent($m[2]);
    }
}
