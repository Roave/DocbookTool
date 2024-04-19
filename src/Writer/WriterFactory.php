<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\Environment;
use Roave\DocbookTool\InteractiveHttpBasicAuthTokenCreator;
use RuntimeException;
use Safe\Exceptions\SafeExceptionInterface;
use Twig\Environment as Twig;

use function count;
use function dirname;
use function file_exists;
use function in_array;
use function Safe\mkdir;
use function sprintf;

class WriterFactory
{
    public function __construct(private readonly Twig $twig, private readonly LoggerInterface $logger)
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
        /** @var list<OutputWriter> $outputWriters */
        $outputWriters = [];

        if (in_array('--html', $arguments, true)) {
            $outputDocbookHtml = Environment::require('DOCBOOK_TOOL_OUTPUT_HTML_FILE');

            if (! file_exists(dirname($outputDocbookHtml))) {
                mkdir(dirname($outputDocbookHtml), recursive: true);
            }

            $this->logger->debug(sprintf(
                '[%s] HTML output requested to file "%s", adding writer: %s',
                self::class,
                $outputDocbookHtml,
                SingleStaticHtmlWriter::class,
            ));

            $outputWriters[] = new SingleStaticHtmlWriter(
                $this->twig,
                'online.twig',
                $outputDocbookHtml,
                $this->logger,
            );
        }

        if (in_array('--pdf', $arguments, true)) {
            $outputPdfPath = Environment::require('DOCBOOK_TOOL_OUTPUT_PDF_PATH');

            if (! file_exists($outputPdfPath)) {
                mkdir($outputPdfPath, recursive: true);
            }

            $this->logger->debug(sprintf(
                '[%s] PDF output requested to directory "%s", adding writer: %s',
                self::class,
                $outputPdfPath,
                MultiplePdfFilesWriter::class,
            ));

            $outputWriters[] = new MultiplePdfFilesWriter(
                $this->twig,
                'pdf.twig',
                'wkhtmltopdf',
                $outputPdfPath,
                $this->logger,
            );
        }

        if (in_array('--confluence', $arguments, true)) {
            $confluenceUrl         = Environment::require('DOCBOOK_TOOL_CONFLUENCE_URL');
            $confluenceAuthToken   = Environment::optional('DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN');
            $skipContentHashChecks = Environment::optionalBoolean('DOCBOOK_TOOL_CONFLUENCE_SKIP_CONTENT_HASH_CHECKS');

            if ($confluenceAuthToken === null && InteractiveHttpBasicAuthTokenCreator::isInteractiveTty()) {
                $confluenceAuthToken = (new InteractiveHttpBasicAuthTokenCreator())();
            }

            if ($confluenceAuthToken !== null) {
                $this->logger->debug(sprintf(
                    '[%s] Confluence output requested to "%s" and auth token is available, adding writer: %s',
                    self::class,
                    $confluenceUrl,
                    ConfluenceWriter::class,
                ));

                $outputWriters[] = new ConfluenceWriter(
                    new Client(['verify' => false]),
                    $confluenceUrl,
                    $confluenceAuthToken,
                    $this->logger,
                    $skipContentHashChecks,
                );
            } else {
                $this->logger->notice(sprintf(
                    '[%s] Skipping Confluence mirror step, DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN was not set and could not be set interactively.',
                    self::class,
                ));
            }
        }

        if (! count($outputWriters)) {
            throw new RuntimeException('No writers specified.');
        }

        return $outputWriters;
    }
}
