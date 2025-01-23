<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest\Formatter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\Formatter\AggregatePageFormatter;
use Roave\DocbookTool\Formatter\MarkdownToHtml;
use Roave\DocbookTool\Formatter\ReplaceGithubMarkdownAlerts;

use function trim;

/** @covers \Roave\DocbookTool\Formatter\ExtractFrontMatter */
final class ReplaceGithubMarkdownAlertsTest extends TestCase
{
    /** @return array<string,array{markdownContent:non-empty-string,expectedContent:non-empty-string}> */
    public static function titleProvider(): array
    {
        return [
            'note' => [
                'markdownContent' => <<<'MD'
                    Some text before
                    
                    > [!NOTE]
                    > This is something worth noting...
                    
                    Some text after
                    MD,
                'expectedContent' => <<<'HTML'
                    <p>Some text before</p>
                    
                    <blockquote class="markdown-alert markdown-alert-note">
                      <p class="markdown-alert-title"><span class="markdown-alert-emoji">ℹ️ </span>Note</p>
                      <p>
                      This is something worth noting...</p>
                    </blockquote>

                    <p>Some text after</p>
                    HTML,
            ],
            'tip' => [
                'markdownContent' => <<<'MD'
                    > [!TIP]
                    > This is a handy tip!
                    MD,
                'expectedContent' => <<<'HTML'
                    <blockquote class="markdown-alert markdown-alert-tip">
                      <p class="markdown-alert-title"><span class="markdown-alert-emoji">💡 </span>Tip</p>
                      <p>
                      This is a handy tip!</p>
                    </blockquote>
                    HTML,
            ],
            'important' => [
                'markdownContent' => <<<'MD'
                    > [!IMPORTANT]
                    > This is very important.
                    MD,
                'expectedContent' => <<<'HTML'
                    <blockquote class="markdown-alert markdown-alert-important">
                      <p class="markdown-alert-title"><span class="markdown-alert-emoji">⁉️ </span>Important</p>
                      <p>
                      This is very important.</p>
                    </blockquote>
                    HTML,
            ],
            'warning' => [
                'markdownContent' => <<<'MD'
                    > [!WARNING]
                    > This is a dire warning...
                    MD,
                'expectedContent' => <<<'HTML'
                    <blockquote class="markdown-alert markdown-alert-warning">
                      <p class="markdown-alert-title"><span class="markdown-alert-emoji">⚠️ </span>Warning</p>
                      <p>
                      This is a dire warning...</p>
                    </blockquote>
                    HTML,
            ],
            'caution' => [
                'markdownContent' => <<<'MD'
                    > [!CAUTION]
                    > You have the right to remain silent.
                    MD,
                'expectedContent' => <<<'HTML'
                    <blockquote class="markdown-alert markdown-alert-caution">
                      <p class="markdown-alert-title"><span class="markdown-alert-emoji">🚨 </span>Caution</p>
                      <p>
                      You have the right to remain silent.</p>
                    </blockquote>
                    HTML,
            ],
        ];
    }

    /**
     * @param non-empty-string $markdownContent
     * @param non-empty-string $expectedContent
     *
     * @dataProvider titleProvider
     */
    public function testGithubMarkdownIsReplaced(string $markdownContent, string $expectedContent): void
    {
        $logger = new NullLogger();
        self::assertSame(
            $expectedContent,
            trim((new AggregatePageFormatter([
                new MarkdownToHtml($logger),
                new ReplaceGithubMarkdownAlerts($logger),
            ]))(DocbookPage::fromSlugAndContent('path', 'slug', $markdownContent))->content()),
        );
    }
}
