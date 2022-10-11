<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest;

use PHPUnit\Framework\TestCase;
use Roave\DocbookTool\DocbookPage;
use RuntimeException;

/** @covers \Roave\DocbookTool\DocbookPage */
final class DocbookPageTest extends TestCase
{
    public function testEmptyPageThrowsExceptionWhenFetchingTitle(): void
    {
        $page = DocbookPage::fromSlugAndContent(__FILE__, 'the-slug', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('First line of markdown file the-slug did not start with "# "');
        /** @noinspection UnusedFunctionResultInspection */
        /** @psalm-suppress UnusedMethodCall */
        $page->title();
    }

    public function testTitleCanBeDeterminedFromContentWithNoFrontMatter(): void
    {
        $content = <<<'HTML'
<h1>This page has no front matter</h1>

Hey
HTML;

        $page = DocbookPage::fromSlugAndContent(__FILE__, 'the-slug', $content);

        self::assertSame('This page has no front matter', $page->title());
    }

    public function testTitleCanBeDeterminedFromFrontMatter(): void
    {
        $content = <<<'HTML'
<h1>This page has front matter</h1>

Hey
HTML;

        $page = DocbookPage::fromSlugAndContent(__FILE__, 'the-slug', $content)
            ->withFrontMatter(['title' => 'Front matter title']);

        self::assertSame('Front matter title', $page->title());
    }
}
