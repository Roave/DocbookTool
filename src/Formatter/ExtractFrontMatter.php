<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Roave\DocbookTool\DocbookPage;
use Symfony\Component\Yaml\Yaml;

use function assert;
use function count;
use function is_array;
use function Safe\preg_match;
use function str_contains;

final class ExtractFrontMatter implements PageFormatter
{
    public function __invoke(DocbookPage $page): DocbookPage
    {
        if (! str_contains($page->content(), '---')) {
            return $page;
        }

        if (! preg_match('/^---\n([^\-]+)---\n([\w\W]*)$/', $page->content(), $m)) {
            return $page;
        }

        assert($m !== null);
        assert(count($m) === 2);

        $frontMatter = Yaml::parse((string) $m[1]);
        assert(is_array($frontMatter));

        return $page
            ->withFrontMatter($frontMatter)
            ->withReplacedContent((string) $m[2]);
    }
}
