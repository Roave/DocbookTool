<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Roave\DocbookTool\DocbookPage;

use function htmlentities;
use function preg_replace_callback;
use function Safe\file_get_contents;

use const ENT_QUOTES;

final class InlineFeatureFile implements PageFormatter
{
    public function __construct(private string $featuresPath)
    {
    }

    public function __invoke(DocbookPage $page): DocbookPage
    {
        return $page->withReplacedContent(
            preg_replace_callback(
                '/{{feature:([a-zA-Z0-9\/.-]+)}}/',
                /** @psalm-param array<int,string> $m */
                function (array $m): string {
                    $feature = file_get_contents($this->featuresPath . '/' . $m[1]);

                    return '<pre><code class="lang-gherkin">' . htmlentities($feature, ENT_QUOTES) . '</code></pre>';
                },
                $page->content()
            )
        );
    }
}
