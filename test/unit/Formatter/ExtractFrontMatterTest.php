<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest\Formatter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\Formatter\AggregatePageFormatter;
use Roave\DocbookTool\Formatter\ExtractFrontMatter;
use Roave\DocbookTool\Formatter\MarkdownToHtml;

/** @covers \Roave\DocbookTool\Formatter\ExtractFrontMatter */
final class ExtractFrontMatterTest extends TestCase
{
    /** @return array<string,array{content:non-empty-string,expectedTitle:non-empty-string}> */
    public function titleProvider(): array
    {
        return [
            'simpleTitle' => [
                'content' => <<<'MD'
---
title: Foo Yay
---
# Bar
MD,
                'expectedTitle' => 'Foo Yay',
            ],
            'quotedTitle' => [
                'content' => <<<'MD'
---
title: "Foo Yay"
---
# Bar
MD,
                'expectedTitle' => 'Foo Yay',
            ],
            'quotedTitleWithColon' => [
                'content' => <<<'MD'
---
title: "Foo: Yay"
---
# Bar
MD,
                'expectedTitle' => 'Foo: Yay',
            ],
            'unquotedWithHyphen' => [
                'content' => <<<'MD'
---
title: Foo - Yay
---
# Bar
MD,
                'expectedTitle' => 'Foo - Yay',
            ],
            'hasFrontMatterButNoTitle' => [
                'content' => <<<'MD'
---
another: value
---
# Bar
MD,
                'expectedTitle' => 'Bar',
            ],
            'noFrontMatter' => [
                'content' => <<<'MD'
# Bar
MD,
                'expectedTitle' => 'Bar',
            ],
        ];
    }

    /**
     * @param non-empty-string $content
     * @param non-empty-string $expectedTitle
     *
     * @dataProvider titleProvider
     */
    public function testTitleCanBeSetInFrontMatter(string $content, string $expectedTitle): void
    {
        $logger = new NullLogger();
        self::assertSame(
            $expectedTitle,
            (new AggregatePageFormatter([
                new ExtractFrontMatter($logger),
                new MarkdownToHtml($logger),
            ]))(DocbookPage::fromSlugAndContent('path', 'slug', $content))->title(),
        );
    }
}
