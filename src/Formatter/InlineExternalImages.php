<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use RuntimeException;

use function base64_encode;
use function dirname;
use function getimagesizefromstring;
use function is_string;
use function preg_replace_callback;
use function sprintf;
use function str_starts_with;
use function trim;

use const PHP_EOL;

final class InlineExternalImages implements PageFormatter
{
    public function __construct(private readonly LoggerInterface $logger, private ClientInterface $client)
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

                    $fullImagePath = 'file://' . dirname($page->path()) . '/' . $imagePath;

                    $this->logger->debug(sprintf('[%s] Inlining image "%s" in page "%s"', self::class, $fullImagePath, $page->slug()));

                    $imageContent = $this->client->sendRequest(new Request('GET', $fullImagePath))->getBody()->getContents();

                    $mime = ((array) getimagesizefromstring($imageContent))['mime'] ?? null;

                    if (! is_string($mime)) {
                        if (str_starts_with($imageContent, '@startuml')) {
                            return sprintf(
                                '```puml%s%s%s```',
                                PHP_EOL,
                                trim($imageContent),
                                PHP_EOL,
                            );
                        }

                        throw new RuntimeException('Unable to determine mime type of ' . $fullImagePath);
                    }

                    return sprintf(
                        '![%s](data:%s;base64,%s)',
                        $altText,
                        $mime,
                        base64_encode($imageContent),
                    );
                },
                $page->content(),
            ),
        );
    }
}
