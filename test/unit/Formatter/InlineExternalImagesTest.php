<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest\Formatter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\Formatter\InlineExternalImages;
use RuntimeException;

use function sprintf;

/** @covers \Roave\DocbookTool\Formatter\InlineExternalImages */
final class InlineExternalImagesTest extends TestCase
{
    private const MIME_JPG = 'image/jpeg';
    private const MIME_PNG = 'image/png';
    private const MIME_GIF = 'image/gif';

    private const EXPECTED_BASE64_CONTENT_TYPES = [
        self::MIME_JPG => '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',
        self::MIME_PNG => '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',
        self::MIME_GIF => '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',
    ];

    /** @return list<array{0:non-empty-string,1:non-empty-string,2:non-empty-string}> */
    public function contentAndImagePathProvider(): array
    {
        return [
            [__DIR__ . '/../../fixture/docbook', 'smile.jpg', self::MIME_JPG],
            [__DIR__ . '/../../fixture/docbook', './smile.jpg', self::MIME_JPG],
            [__DIR__ . '/../../fixture/docbook/', 'smile.jpg', self::MIME_JPG],
            [__DIR__ . '/../../fixture/docbook/', './smile.jpg', self::MIME_JPG],
            [__DIR__ . '/../../fixture', './docbook/smile.jpg', self::MIME_JPG],
            [__DIR__ . '/../../fixture', 'docbook/smile.jpg', self::MIME_JPG],
            [__DIR__ . '/../../fixture/', './docbook/smile.jpg', self::MIME_JPG],
            [__DIR__ . '/../../fixture/', 'docbook/smile.jpg', self::MIME_JPG],
            [__DIR__ . '/../../fixture/docbook', 'smile.png', self::MIME_PNG],
            [__DIR__ . '/../../fixture/docbook', './smile.png', self::MIME_PNG],
            [__DIR__ . '/../../fixture/docbook/', 'smile.png', self::MIME_PNG],
            [__DIR__ . '/../../fixture/docbook/', './smile.png', self::MIME_PNG],
            [__DIR__ . '/../../fixture', './docbook/smile.png', self::MIME_PNG],
            [__DIR__ . '/../../fixture', 'docbook/smile.png', self::MIME_PNG],
            [__DIR__ . '/../../fixture/', './docbook/smile.png', self::MIME_PNG],
            [__DIR__ . '/../../fixture/', 'docbook/smile.png', self::MIME_PNG],
            [__DIR__ . '/../../fixture/docbook', 'smile.gif', self::MIME_GIF],
            [__DIR__ . '/../../fixture/docbook', './smile.gif', self::MIME_GIF],
            [__DIR__ . '/../../fixture/docbook/', 'smile.gif', self::MIME_GIF],
            [__DIR__ . '/../../fixture/docbook/', './smile.gif', self::MIME_GIF],
            [__DIR__ . '/../../fixture', './docbook/smile.gif', self::MIME_GIF],
            [__DIR__ . '/../../fixture', 'docbook/smile.gif', self::MIME_GIF],
            [__DIR__ . '/../../fixture/', './docbook/smile.gif', self::MIME_GIF],
            [__DIR__ . '/../../fixture/', 'docbook/smile.gif', self::MIME_GIF],
        ];
    }

    /**
     * @param non-empty-string $contentPath
     * @param non-empty-string $imagePath
     * @param non-empty-string $expectedMimeType
     *
     * @dataProvider contentAndImagePathProvider
     */
    public function testExternalImagesAreInlined(string $contentPath, string $imagePath, string $expectedMimeType): void
    {
        $markdown = <<<MD
Here is some markdown
![the alt text]($imagePath)
More markdown
MD;

        $page = DocbookPage::fromSlugAndContent('slug', $markdown);

        $formattedPage = (new InlineExternalImages($contentPath, new NullLogger()))($page);

        $expectedOutput = sprintf(
            <<<'MD'
Here is some markdown
![the alt text](data:%s;base64,%s)
More markdown
MD,
            $expectedMimeType,
            self::EXPECTED_BASE64_CONTENT_TYPES[$expectedMimeType],
        );

        self::assertSame($expectedOutput, $formattedPage->content());
    }

    public function testImageNotExisting(): void
    {
        $this->expectError();
        $this->expectErrorMessage('Failed to open stream: No such file or directory');
        (new InlineExternalImages(__DIR__ . '/../../fixture/docbook', new NullLogger()))(
            DocbookPage::fromSlugAndContent('slug', '![the alt text](something-that-should-not-exist.jpg)'),
        );
    }

    public function testImageMimeTypeNotDetected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to determine mime type');
        (new InlineExternalImages(__DIR__ . '/../../fixture/docbook', new NullLogger()))(
            DocbookPage::fromSlugAndContent('slug', '![the alt text](test.md)'),
        );
    }
}
