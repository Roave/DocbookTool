<?php

declare(strict_types=1);

namespace Roave\DocbookToolIntegrationTest;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Roave\DocbookTool\Formatter\AggregatePageFormatter;
use Roave\DocbookTool\Formatter\ExtractFrontMatter;
use Roave\DocbookTool\Formatter\InlineCodeFromFile;
use Roave\DocbookTool\Formatter\InlineExternalImages;
use Roave\DocbookTool\Formatter\InlineFeatureFile;
use Roave\DocbookTool\Formatter\MarkdownToHtml;
use Roave\DocbookTool\Formatter\RenderPlantUmlDiagramInline;
use Roave\DocbookTool\RecursivelyLoadPagesFromPath;
use Roave\DocbookTool\RetrieveLocalFileContents;
use Roave\DocbookTool\SortThePages;
use Roave\DocbookTool\WriteAllTheOutputs;
use Roave\DocbookTool\Writer\MultiplePdfFilesWriter;
use Roave\DocbookTool\Writer\SingleStaticHtmlWriter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function array_map;
use function Psl\Filesystem\delete_file;
use function Psl\Filesystem\exists;

final class DocbookToolGeneratorTest extends TestCase
{
    private const string TEMPLATE_PATH                = __DIR__ . '/../fixture/templates';
    private const string OUTPUT_DOCBOOK_HTML          = __DIR__ . '/out.html';
    private const string EXPECTED_OUTPUT_DOCBOOK_HTML = __DIR__ . '/../fixture/expectations/out.html';
    private const string OUTPUT_PDF_PATH              = __DIR__;
    private const string FEATURES_PATH                = __DIR__ . '/../fixture/feature';
    private const string CONTENT_PATH                 = __DIR__ . '/../fixture/docbook';

    public function testGeneration(): void
    {
        $twig                 = new Environment(new FilesystemLoader(self::TEMPLATE_PATH));
        $logger               = new NullLogger();
        $retrieveFileContents = new RetrieveLocalFileContents();

        (new WriteAllTheOutputs([
            new SingleStaticHtmlWriter($twig, 'online.twig', self::OUTPUT_DOCBOOK_HTML, $logger),
            new MultiplePdfFilesWriter($twig, 'pdf.twig', 'wkhtmltopdf', self::OUTPUT_PDF_PATH, $logger),
        ]))(
            (new SortThePages($logger))(
                array_map(
                    [
                        new AggregatePageFormatter([
                            new ExtractFrontMatter($logger),
                            new InlineExternalImages($logger, $retrieveFileContents),
                            new RenderPlantUmlDiagramInline($logger),
                            new MarkdownToHtml($logger),
                            new InlineCodeFromFile(self::CONTENT_PATH, $logger, $retrieveFileContents),
                            new InlineFeatureFile(self::FEATURES_PATH, $logger, $retrieveFileContents),
                        ]),
                        '__invoke',
                    ],
                    (new RecursivelyLoadPagesFromPath($logger))(self::CONTENT_PATH),
                ),
            ),
        );

        self::assertFileMatchesFormatFile(
            self::EXPECTED_OUTPUT_DOCBOOK_HTML,
            self::OUTPUT_DOCBOOK_HTML,
        );
        self::assertFileExists(self::OUTPUT_PDF_PATH . '/test.pdf');
        // @todo assert PDFs are the same - https://github.com/Roave/DocbookTool/issues/3
    }

    public function tearDown(): void
    {
        parent::tearDown();

        if (exists(self::OUTPUT_DOCBOOK_HTML)) {
            delete_file(self::OUTPUT_DOCBOOK_HTML);
        }

        if (! exists(self::OUTPUT_PDF_PATH . '/test.pdf')) {
            return;
        }

        delete_file(self::OUTPUT_PDF_PATH . '/test.pdf');
    }
}
