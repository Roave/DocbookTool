<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use Override;
use Psl\File\Exception\ExceptionInterface;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use Twig\Environment as Twig;

use function Psl\File\write;
use function sprintf;

final class SingleStaticHtmlWriter implements OutputWriter
{
    /** @param non-empty-string $outputFile */
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
     * @throws ExceptionInterface
     */
    #[Override]
    public function __invoke(array $docbookPages): void
    {
        $this->logger->info(sprintf('[%s] Writing HTML output to %s', self::class, $this->outputFile));
        write(
            $this->outputFile,
            $this->twig->render($this->twigTemplate, ['pages' => $docbookPages]),
        );
        $this->logger->debug(sprintf('[%s] HTML rendering completed.', self::class));
    }
}
