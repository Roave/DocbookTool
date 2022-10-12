<?php

declare(strict_types=1);

namespace Roave\DocbookToolIntegrationTest\Writer;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\Writer\MultiplePdfFilesWriter;
use Twig\Environment as Twig;

use function file_exists;
use function unlink;

/** @covers \Roave\DocbookTool\Writer\MultiplePdfFilesWriter */
final class MultiplePdfFilesWriterTest extends TestCase
{
    private const OUTPUT_PDF_PATH = __DIR__;

    public function testFileNotFoundInHtmlDoesNotCrashPdfGeneration(): void
    {
        if (file_exists(self::OUTPUT_PDF_PATH . '/slug.pdf')) {
            unlink(self::OUTPUT_PDF_PATH . '/slug.pdf');
        }

        $twig = $this->createMock(Twig::class);
        $twig->expects(self::once())
            ->method('render')
            ->willReturn(
                <<<'HTML'
<!DOCTYPE html>
<html lang="en" >
<head>
    <meta charset="utf-8"/>
    <title>Documentation</title>
    <script type="text/javascript" src="https://roave.com/not-here"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.22.0/themes/prism.css" rel="stylesheet" />
</head>
<body>
hi
<pre class=" language-dotenv"><code class=" language-dotenv">
SOMETHING=something
</code></pre>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.22.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.22.0/plugins/autoloader/prism-autoloader.js"></script>
</body>
</html>
HTML,
            );

        $logger = new NullLogger();

        $writer = new MultiplePdfFilesWriter(
            $twig,
            'pdf.twig',
            'wkhtmltopdf',
            self::OUTPUT_PDF_PATH,
            $logger,
        );

        $page = DocbookPage::fromSlugAndContent('slug', 'content')
            ->withFrontMatter(['pdf' => true]);

        $writer->__invoke([$page]);

        self::assertFileExists(self::OUTPUT_PDF_PATH . '/slug.pdf');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        if (! file_exists(self::OUTPUT_PDF_PATH . '/slug.pdf')) {
            return;
        }

        unlink(self::OUTPUT_PDF_PATH . '/slug.pdf');
    }
}
