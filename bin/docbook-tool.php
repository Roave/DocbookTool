<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use Psr\Log\NullLogger;
use Roave\DocbookTool\Formatter\ExtractFrontMatter;
use Roave\DocbookTool\Formatter\InlineFeatureFile;
use Roave\DocbookTool\Formatter\MarkdownToHtml;
use Roave\DocbookTool\Formatter\RenderPlantUmlDiagramInline;
use Roave\DocbookTool\RecursivelyLoadPagesFromPath;
use Roave\DocbookTool\Writer\ConfluenceWriter;
use Roave\DocbookTool\Writer\MultiplePdfFilesWriter;
use Roave\DocbookTool\Writer\SingleStaticHtmlWriter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function Safe\mkdir;

require_once __DIR__ . '/../vendor/autoload.php';

(static function (): void {
    $contentPath       = getenv('DOCBOOK_TOOL_CONTENT_PATH') ?: '/app/docs/book';
    $templatePath      = getenv('DOCBOOK_TOOL_TEMPLATE_PATH') ?: '/app/templates';
    $featuresPath      = getenv('DOCBOOK_TOOL_FEATURES_PATH') ?: '/app/features';
    $outputDocbookHtml = getenv('DOCBOOK_TOOL_OUTPUT_HTML_FILE') ?: '/docs-package/docbook.html';
    $outputPdfPath     = getenv('DOCBOOK_TOOL_OUTPUT_PDF_PATH') ?: '/docs-package/pdf';

    $twig = new Environment(new FilesystemLoader($templatePath));
    $logger = new NullLogger(); // @todo

    if (!file_exists(dirname($outputDocbookHtml))) {
        mkdir(dirname($outputDocbookHtml), recursive: true);
    }
    if (!file_exists($outputPdfPath)) {
        mkdir($outputPdfPath, recursive: true);
    }

    (new WriteAllTheOutputs([
        new SingleStaticHtmlWriter($twig, 'online.twig', $outputDocbookHtml),
        new MultiplePdfFilesWriter($twig, 'pdf.twig', 'wkhtmltopdf', $outputPdfPath, $logger),
        new ConfluenceWriter()
    ]))->__invoke(
        (new FormatAllThePages([
            new ExtractFrontMatter(),
            new RenderPlantUmlDiagramInline(),
            new MarkdownToHtml(),
            new InlineFeatureFile($featuresPath),
        ]))->__invoke(
            (new RecursivelyLoadPagesFromPath())->__invoke($contentPath)
        )
    );
})();
