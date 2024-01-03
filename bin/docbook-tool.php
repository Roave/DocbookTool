<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use Jasny\Twig\ArrayExtension;
use Jasny\Twig\PcreExtension;
use Jasny\Twig\TextExtension;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Roave\DocbookTool\Formatter\AggregatePageFormatter;
use Roave\DocbookTool\Formatter\ExtractFrontMatter;
use Roave\DocbookTool\Formatter\InlineCodeFromFile;
use Roave\DocbookTool\Formatter\InlineExternalImages;
use Roave\DocbookTool\Formatter\InlineFeatureFile;
use Roave\DocbookTool\Formatter\MarkdownToHtml;
use Roave\DocbookTool\Formatter\RenderPlantUmlDiagramInline;
use Roave\DocbookTool\Writer\WriterFactory;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

use function array_map;
use function is_string;

(static function (array $arguments): void {
    require_once __DIR__ . '/../vendor/autoload.php';

    $contentPath  = Environment::require('DOCBOOK_TOOL_CONTENT_PATH');
    $templatePath = Environment::require('DOCBOOK_TOOL_TEMPLATE_PATH');
    $featuresPath = Environment::optional('DOCBOOK_TOOL_FEATURES_PATH');

    $twig = new Twig(new FilesystemLoader($templatePath));
    $twig->addExtension(new PcreExtension());
    $twig->addExtension(new TextExtension());
    $twig->addExtension(new ArrayExtension());

    $logger = new Logger('cli');
    $logger->pushHandler(new StreamHandler('php://stdout'));

    $outputWriters = (new WriterFactory($twig, $logger))($arguments);

    $pageFormatters = [
        new ExtractFrontMatter($logger),
        new InlineExternalImages($logger),
        new RenderPlantUmlDiagramInline($logger),
        new MarkdownToHtml($logger),
        new InlineCodeFromFile($contentPath, $logger),
    ];

    if (is_string($featuresPath)) {
        $pageFormatters[] = new InlineFeatureFile($featuresPath, $logger);
    }

    (new WriteAllTheOutputs($outputWriters))(
        (new SortThePages($logger))(
            array_map(
                [new AggregatePageFormatter($pageFormatters), '__invoke'],
                (new RecursivelyLoadPagesFromPath($logger))($contentPath),
            ),
        ),
    );
})($argv);
