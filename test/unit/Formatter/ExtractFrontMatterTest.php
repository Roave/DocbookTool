<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest\Formatter;

use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\Formatter\AggregatePageFormatter;
use Roave\DocbookTool\Formatter\ExtractFrontMatter;
use PHPUnit\Framework\TestCase;
use Roave\DocbookTool\Formatter\MarkdownToHtml;

/** @covers \Roave\DocbookTool\Formatter\ExtractFrontMatter */
final class ExtractFrontMatterTest extends TestCase
{
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

    /** @dataProvider titleProvider */
    public function testTitleCanBeSetInFrontMatter(string $content, string $expectedTitle): void
    {
        self::assertSame(
            $expectedTitle,
            (new AggregatePageFormatter([
                new ExtractFrontMatter(),
                new MarkdownToHtml(),
            ]))(DocbookPage::fromSlugAndContent('slug', $content))->title()
        );
    }
}
