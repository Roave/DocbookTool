<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest\Formatter;

use PHPUnit\Framework\TestCase;
use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\Formatter\InlineExternalImages;

/** @covers \Roave\DocbookTool\Formatter\InlineExternalImages */
final class InlineExternalImagesTest extends TestCase
{
    /** @return list<array{0:non-empty-string,1:non-empty-string}> */
    public function contentAndImagePathProvider(): array
    {
        return [
            [__DIR__ . '/../../fixture/docbook', 'smile.png'],
            [__DIR__ . '/../../fixture/docbook', './smile.png'],
            [__DIR__ . '/../../fixture/docbook/', 'smile.png'],
            [__DIR__ . '/../../fixture/docbook/', './smile.png'],
            [__DIR__ . '/../../fixture', './docbook/smile.png'],
            [__DIR__ . '/../../fixture', 'docbook/smile.png'],
            [__DIR__ . '/../../fixture/', './docbook/smile.png'],
            [__DIR__ . '/../../fixture/', 'docbook/smile.png'],
        ];
    }

    /**
     * @param non-empty-string $contentPath
     * @param non-empty-string $imagePath
     *
     * @dataProvider contentAndImagePathProvider
     */
    public function testExternalImagesAreInlined(string $contentPath, string $imagePath): void
    {
        $markdown = <<<MD
Here is some markdown
![the alt text]($imagePath)
More markdown
MD;

        $page = DocbookPage::fromSlugAndContent('slug', $markdown);

        $formattedPage = (new InlineExternalImages($contentPath))($page);

        $expectedOutput = <<<'MD'
Here is some markdown
![the alt text](data:image/png;base64,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)
More markdown
MD;

        self::assertSame($expectedOutput, $formattedPage->content());
    }
}
