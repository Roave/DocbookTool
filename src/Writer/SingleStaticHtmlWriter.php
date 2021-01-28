<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use Roave\DocbookTool\DocbookPage;
use Twig\Environment;

use function Safe\file_put_contents;

final class SingleStaticHtmlWriter implements OutputWriter
{
    public function __construct(
        private Environment $twig,
        private string $twigTemplate,
        private string $outputFile
    ) {
    }

    /** @param DocbookPage[] $docbookPages */
    public function __invoke(array $docbookPages): void
    {
        file_put_contents(
            $this->outputFile,
            $this->twig->render($this->twigTemplate, ['pages' => $docbookPages])
        );
    }
}
