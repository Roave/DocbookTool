<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use RuntimeException;
use Safe\Exceptions\SafeExceptionInterface;
use Twig\Environment as Twig;
use Twig\Error\Error as TwigException;

use function count;
use function escapeshellcmd;
use function exec;
use function file_exists;
use function implode;
use function Safe\file_put_contents;
use function Safe\unlink;
use function sprintf;
use function sys_get_temp_dir;

class MultiplePdfFilesWriter implements OutputWriter
{
    public function __construct(
        private Twig $twig,
        private string $twigTemplate,
        private string $locationOfWkhtmltopdf,
        private string $pdfOutputPath,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param DocbookPage[] $docbookPages
     *
     * @throws RuntimeException
     * @throws SafeExceptionInterface
     * @throws TwigException
     */
    public function __invoke(array $docbookPages): void
    {
        foreach ($docbookPages as $page) {
            if (! $page->shouldGeneratePdf()) {
                continue;
            }

            $this->logger->info(sprintf("Rendering %s.pdf ...\n", $page->slug()));

            $tmpHtmlFile = sys_get_temp_dir() . '/' . $page->slug() . '.html';
            $pdfFile     = $this->pdfOutputPath . '/' . $page->slug() . '.pdf';
            file_put_contents($tmpHtmlFile, $this->twig->render($this->twigTemplate, ['page' => $page]));

            exec(
                escapeshellcmd(implode(
                    ' ',
                    [
                        $this->locationOfWkhtmltopdf,
                        '--enable-local-file-access',
                        '--load-error-handling',
                        'ignore',
                        '--load-media-error-handling',
                        'ignore',
                        $tmpHtmlFile,
                        $pdfFile,
                    ],
                )) . ' 2>&1',
                $output,
                $exitCode,
            );

            if (count($output) > 0) {
                /** @psalm-var list<string> $output */
                $this->logger->debug('wkhtmltopdf output: ' . implode("\n", $output));
            }

            unlink($tmpHtmlFile);

            /**
             * Previously, we'd check the exit code, but it seems it is not reliable. I observed that the PDF was still
             * generated successfully, despite the 404 (e.g. in {@see \Roave\DocbookToolIntegrationTest\Writer\MultiplePdfFilesWriterTest::testFileNotFoundInHtmlDoesNotCrashPdfGeneration}
             * and the flags `--load-error-handling ignore` and `--load-media-error-handling ignore` do not seem to
             * have any effect on the exit code. Therefore, next best thing (although it may slip failures through
             * unnoticed) is to just check the file is generated.
             *
             * @link https://github.com/wkhtmltopdf/wkhtmltopdf/issues/2051
             *
             * Note: there is a separate issue {@link https://github.com/Roave/DocbookTool/issues/3} to add testing
             * around the actual PDF contents, in the backlog...
             */
            if (! file_exists($pdfFile)) {
                throw new RuntimeException('Failed to generate PDF. Output was: ' . implode("\n", $output));
            }
        }
    }
}
