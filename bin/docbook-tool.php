<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Roave\DocbookTool\Formatter\ExtractFrontMatter;
use Roave\DocbookTool\Formatter\InlineFeatureFile;
use Roave\DocbookTool\Formatter\MarkdownToHtml;
use Roave\DocbookTool\Formatter\RenderPlantUmlDiagramInline;
use Roave\DocbookTool\Writer\ConfluenceWriter;
use Roave\DocbookTool\Writer\MultiplePdfFilesWriter;
use Roave\DocbookTool\Writer\OutputWriter;
use Roave\DocbookTool\Writer\SingleStaticHtmlWriter;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function count;
use function dirname;
use function file_exists;
use function getenv;
use function in_array;
use function is_string;
use function Safe\mkdir;

require_once __DIR__ . '/../vendor/autoload.php';

(static function (array $arguments): void {
    $contentPath  = getenv('DOCBOOK_TOOL_CONTENT_PATH') ?: '/app/docs/book';
    $templatePath = getenv('DOCBOOK_TOOL_TEMPLATE_PATH') ?: '/app/templates';
    $featuresPath = getenv('DOCBOOK_TOOL_FEATURES_PATH') ?: null;

    $twig = new Environment(new FilesystemLoader($templatePath));

    $logger = new Logger('cli');
    $logger->pushHandler(new StreamHandler('php://stdout'));

    /** @var OutputWriter[] $outputWriters */
    $outputWriters = [];

    if (in_array('--html', $arguments, true)) {
        $outputDocbookHtml = getenv('DOCBOOK_TOOL_OUTPUT_HTML_FILE') ?: '/docs-package/docbook.html';

        if (! file_exists(dirname($outputDocbookHtml))) {
            mkdir(dirname($outputDocbookHtml), recursive: true);
        }

        $outputWriters[] = new SingleStaticHtmlWriter(
            $twig,
            'online.twig',
            $outputDocbookHtml,
            $logger
        );
    }

    if (in_array('--pdf', $arguments, true)) {
        $outputPdfPath = getenv('DOCBOOK_TOOL_OUTPUT_PDF_PATH') ?: '/docs-package/pdf';

        if (! file_exists($outputPdfPath)) {
            mkdir($outputPdfPath, recursive: true);
        }

        $outputWriters[] = new MultiplePdfFilesWriter(
            $twig,
            'pdf.twig',
            'wkhtmltopdf',
            $outputPdfPath,
            $logger
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
                $logger
            );
        } else {
            $logger->notice('Skipping Confluence mirror step, DOCBOOK_TOOL_CONFLUENCE_URL and/or DOCBOOK_TOOL_CONFLUENCE_AUTH_TOKEN was not set');
        }
    }

    if (! count($outputWriters)) {
        throw new RuntimeException('No writers specified.');
    }

    $pageFormatters = [
        new ExtractFrontMatter(),
        new RenderPlantUmlDiagramInline(),
        new MarkdownToHtml(),
    ];

    if (is_string($featuresPath)) {
        $pageFormatters[] = new InlineFeatureFile($featuresPath);
    }

    (new WriteAllTheOutputs($outputWriters))(
        (new FormatAllThePages($pageFormatters))(
            (new RecursivelyLoadPagesFromPath())($contentPath)
        )
    );
})($argv);
