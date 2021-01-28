<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Roave\DocbookTool\Formatter\AggregatePageFormatter;
use Roave\DocbookTool\Formatter\ExtractFrontMatter;
use Roave\DocbookTool\Formatter\InlineFeatureFile;
use Roave\DocbookTool\Formatter\MarkdownToHtml;
use Roave\DocbookTool\Formatter\RenderPlantUmlDiagramInline;
use Roave\DocbookTool\Writer\WriterFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function array_map;
use function getenv;
use function is_string;

(static function (array $arguments): void {
    require_once __DIR__ . '/../vendor/autoload.php';

    $contentPath  = getenv('DOCBOOK_TOOL_CONTENT_PATH') ?: '/app/docs/book';
    $templatePath = getenv('DOCBOOK_TOOL_TEMPLATE_PATH') ?: '/app/templates';
    $featuresPath = getenv('DOCBOOK_TOOL_FEATURES_PATH') ?: null;

    $twig = new Environment(new FilesystemLoader($templatePath));

    $logger = new Logger('cli');
    $logger->pushHandler(new StreamHandler('php://stdout'));

    $outputWriters = (new WriterFactory($twig, $logger))($arguments);

    $pageFormatters = [
        new ExtractFrontMatter(),
        new RenderPlantUmlDiagramInline(),
        new MarkdownToHtml(),
    ];

    if (is_string($featuresPath)) {
        $pageFormatters[] = new InlineFeatureFile($featuresPath);
    }

    (new WriteAllTheOutputs($outputWriters))(
        array_map(
            [new AggregatePageFormatter($pageFormatters), '__invoke'],
            (new RecursivelyLoadPagesFromPath())($contentPath)
        )
    );
})($argv);
