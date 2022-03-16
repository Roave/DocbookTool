<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use InvalidArgumentException;
use Roave\DocbookTool\DocbookPage;
use Safe\Exceptions\SafeExceptionInterface;
use Symfony\Component\Yaml\Yaml;
use Webmozart\Assert\Assert;

use function Safe\preg_match;
use function str_contains;

final class ExtractFrontMatter implements PageFormatter
{
    /**
     * @throws SafeExceptionInterface
     * @throws InvalidArgumentException
     */
    public function __invoke(DocbookPage $page): DocbookPage
    {
        if (! str_contains($page->content(), '---')) {
            return $page;
        }

        if (! preg_match('/^---\n([\w\W]+?)\n---\n([\w\W]*)$/', $page->content(), $m)) {
            return $page;
        }

        Assert::notNull($m);
        Assert::count($m, 3);

        $frontMatter = Yaml::parse($m[1]);

        Assert::isArray($frontMatter);

        return $page
            ->withFrontMatter($frontMatter)
            ->withReplacedContent($m[2]);
    }
}
