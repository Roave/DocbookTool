<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Roave\DocbookTool\DocbookPage;
use RuntimeException;

use function base64_encode;
use function preg_replace_callback;
use function Safe\file_get_contents;
use function sprintf;

final class InlineExternalImages implements PageFormatter
{
    public function __construct(private readonly string $docbookPath)
    {
    }

    /** @throws RuntimeException */
    public function __invoke(DocbookPage $page): DocbookPage
    {
        return $page->withReplacedContent(
            preg_replace_callback(
                '/!\[([\w\W]+)]\(([\w\W]*?)\)/',
                function (array $m) {
                    /** @var array{1: string, 2: string} $m */
                    $altText   = $m[1];
                    $imagePath = $m[2];

                    $imageContent = file_get_contents($this->docbookPath . '/' . $imagePath);

                    return sprintf(
                        '![%s](data:image/png;base64,%s)',
                        $altText,
                        base64_encode($imageContent),
                    );
                },
                $page->content(),
            ),
        );
    }
}
