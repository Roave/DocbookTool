<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use RuntimeException;

use function array_key_exists;
use function base64_encode;
use function dirname;
use function getimagesize;
use function is_array;
use function is_string;
use function preg_replace_callback;
use function Safe\file_get_contents;
use function sprintf;

final class InlineExternalImages implements PageFormatter
{
    public function __construct(private readonly string $docbookPath, private readonly LoggerInterface $logger)
    {
    }

    /** @throws RuntimeException */
    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Checking if external images can be inlined in %s', self::class, $page->slug()));

        return $page->withReplacedContent(
            preg_replace_callback(
                '/!\[([^]]+)]\(([^)]*?)\)/',
                function (array $m) use ($page) {
                    /** @var array{1: string, 2: string} $m */
                    $altText   = $m[1];
                    $imagePath = $m[2];

                    $fullImagePath = dirname($page->path()) . '/' . $imagePath;

                    $this->logger->debug(sprintf('[%s] Inlining image "%s" in page "%s"', self::class, $fullImagePath, $page->slug()));

                    $imageContent = file_get_contents($fullImagePath);

                    $imageInfo = getimagesize($fullImagePath);

                    if (! is_array($imageInfo) || ! array_key_exists('mime', $imageInfo) || ! is_string($imageInfo['mime'])) {
                        throw new RuntimeException('Unable to determine mime type of ' . $fullImagePath);
                    }

                    return sprintf(
                        '![%s](data:%s;base64,%s)',
                        $altText,
                        $imageInfo['mime'],
                        base64_encode($imageContent),
                    );
                },
                $page->content(),
            ),
        );
    }
}
