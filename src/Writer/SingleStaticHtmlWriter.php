<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use Safe\Exceptions\SafeExceptionInterface;
use Twig\Environment as Twig;

use function Safe\file_put_contents;
use function sprintf;

final class SingleStaticHtmlWriter implements OutputWriter
{
    public function __construct(
        private Twig $twig,
        private string $twigTemplate,
        private string $outputFile,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param DocbookPage[] $docbookPages
     *
     * @throws SafeExceptionInterface
     */
    public function __invoke(array $docbookPages): void
    {
        $this->logger->info(sprintf('[%s] Writing HTML output to %s', self::class, $this->outputFile));
        file_put_contents(
            $this->outputFile,
            $this->twig->render($this->twigTemplate, ['pages' => $docbookPages]),
        );
        $this->logger->debug(sprintf('[%s] HTML rendering completed.', self::class));
    }
}
