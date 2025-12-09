<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use InvalidArgumentException;
use Override;
use Psl\Regex;
use Psl\Regex\Exception\ExceptionInterface;
use Psl\Type;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use Symfony\Component\Yaml\Yaml;

use function count;
use function Psl\invariant;
use function sprintf;
use function str_contains;

final class ExtractFrontMatter implements PageFormatter
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
    #[Override]
    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Checking page "%s" for YAML front matter', self::class, $page->slug()));

        if (! str_contains($page->content(), '---')) {
            $this->logger->debug(sprintf('[%s] Page "%s" does not have any front matter', self::class, $page->slug()));

            return $page;
        }

        $m = Regex\first_match($page->content(), '/^---\n([\w\W]+?)\n---\n([\w\W]*)$/', Type\vec(Type\string()));

        if ($m === null) {
            $this->logger->debug(sprintf('[%s] Page "%s" front matter does not appear correctly formatted, ignoring it', self::class, $page->slug()));

            return $page;
        }

        invariant(count($m) === 3, 'Exactly 3 elements should be matched');

        $frontMatter = Type\dict(Type\array_key(), Type\mixed())
            ->coerce(Yaml::parse($m[1]));

        $this->logger->debug(sprintf('[%s] Successfully extracted front matter from page "%s"', self::class, $page->slug()));

        return $page
            ->withFrontMatter($frontMatter)
            ->withReplacedContent($m[2]);
    }
}
