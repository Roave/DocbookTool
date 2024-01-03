<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest\Formatter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\Formatter\InlineCodeFromFile;

/** @covers \Roave\DocbookTool\Formatter\InlineCodeFromFile */
final class InlineCodeFromFileTest extends TestCase
{
    public function testExternalSourceCodeIsInlined(): void
    {
        $markdown = <<<'MD'
Here is some markdown
{{src-json:example.json}}
More markdown
MD;

        $page = DocbookPage::fromSlugAndContent('/faked.md', 'slug', $markdown);

        $formattedPage = (new InlineCodeFromFile(__DIR__ . '/../../fixture/docbook', new NullLogger()))($page);

        self::assertSame(
            <<<'MD'
Here is some markdown
<pre><code class="lang-json">{
    &quot;example&quot;: &quot;json&quot;
}
</code></pre>
More markdown
MD,
            $formattedPage->content(),
        );
    }
}
