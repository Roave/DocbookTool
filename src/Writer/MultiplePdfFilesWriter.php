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
        private LoggerInterface $logger
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
                        '-q',
                        $tmpHtmlFile,
                        $pdfFile,
                    ]
                )) . ' 2>&1',
                $output,
                $exitCode
            );

            if (count($output) > 0) {
                /** @psalm-var list<string> $output */
                $this->logger->debug('wkhtmltopdf output: ' . implode("\n", $output));
            }

            unlink($tmpHtmlFile);

            if ($exitCode !== 0) {
                throw new RuntimeException('Failed to generate PDF. Output was: ' . implode("\n", $output));
            }
        }
    }
}
