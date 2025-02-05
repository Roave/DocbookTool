<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Psl\Regex;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;

use function array_keys;
use function implode;
use function sprintf;
use function strtolower;
use function ucfirst;

final class ReplaceGithubMarkdownAlerts implements PageFormatter
{
    private const ALERT_FLAVOURS = [
        'NOTE' => 'ℹ️',
        'TIP' => '💡',
        'IMPORTANT' => '⁉️',
        'WARNING' => '⚠️',
        'CAUTION' => '🚨',
    ];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Making replacements for GFM alerts in %s', self::class, $page->slug()));

        return $page->withReplacedContent(
            Regex\replace_with(
                $page->content(),
                '/(<blockquote>)\s*<p>(\[!(' . implode('|', array_keys(self::ALERT_FLAVOURS)) . ')])/ms',
                function (array $m) use ($page): string {
                    $flavour = $m[3];

                    $this->logger->debug(sprintf('[%s] Replacing "%s" GFM alert in %s', self::class, $flavour, $page->slug()));

                    return sprintf(
                        <<<'EOS'
                        <blockquote class="markdown-alert markdown-alert-%s">
                          <p class="markdown-alert-title"><span class="markdown-alert-emoji">%s </span>%s</p>
                          <p>
                        EOS,
                        strtolower($flavour),
                        self::ALERT_FLAVOURS[$flavour],
                        ucfirst(strtolower($flavour)),
                    );
                },
            ),
        );
    }
}
