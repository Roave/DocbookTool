<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\InteractiveHttpBasicAuthTokenCreator;
use RuntimeException;
use Safe\Exceptions\SafeExceptionInterface;
use Twig\Environment;

use function count;
use function dirname;
use function file_exists;
use function getenv;
use function in_array;
use function Safe\mkdir;

class WriterFactory
{
    public function __construct(private Environment $twig, private LoggerInterface $logger)
    {
    }

    /**
     * @param array<array-key, mixed> $arguments
     *
     * @return non-empty-list<OutputWriter>
     *
     * @throws SafeExceptionInterface
     */
    public function __invoke(array $arguments): array
    {
        /** @var non-empty-list<OutputWriter> $outputWriters */
        $outputWriters = [];

        if (in_array('--html', $arguments, true)) {
            $outputDocbookHtml = getenv('DOCBOOK_TOOL_OUTPUT_HTML_FILE') ?: '/docs-package/docbook.html';

            if (! file_exists(dirname($outputDocbookHtml))) {
                mkdir(dirname($outputDocbookHtml), recursive: true);
            }

            $outputWriters[] = new SingleStaticHtmlWriter(
                $this->twig,
                'online.twig',
                $outputDocbookHtml,
                $this->logger
            );
        }

        if (in_array('--pdf', $arguments, true)) {
            $outputPdfPath = getenv('DOCBOOK_TOOL_OUTPUT_PDF_PATH') ?: '/docs-package/pdf';

            if (! file_exists($outputPdfPath)) {
                mkdir($outputPdfPath, recursive: true);
            }

            $outputWriters[] = new MultiplePdfFilesWriter(
                $this->twig,
                'pdf.twig',
                'wkhtmltopdf',
                $outputPdfPath,
                $this->logger
            );
        }

        if (in_array('--confluence', $arguments, true)) {
            $confluenceUrl       = getenv('DOCBOOK_TOOL_CONFLUENCE_URL') ?: null;
            $confluenceAuthToken = getenv('DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN') ?: null;

            if ($confluenceAuthToken === null && InteractiveHttpBasicAuthTokenCreator::isInteractiveTty()) {
                $confluenceAuthToken = (new InteractiveHttpBasicAuthTokenCreator())();
            }

            if ($confluenceUrl !== null && $confluenceAuthToken !== null) {
                $outputWriters[] = new ConfluenceWriter(
                    new Client(['verify' => false]),
                    $confluenceUrl . '/rest/api/content',
                    $confluenceAuthToken,
                    $this->logger
                );
            } else {
                $this->logger->notice('Skipping Confluence mirror step, DOCBOOK_TOOL_CONFLUENCE_URL and/or DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN was not set');
            }
        }

        if (! count($outputWriters)) {
            throw new RuntimeException('No writers specified.');
        }

        return $outputWriters;
    }
}
