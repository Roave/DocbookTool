<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Webmozart\Assert\Assert;

use function ltrim;
use function Safe\file_get_contents;
use function sprintf;
use function str_replace;

class RecursivelyLoadPagesFromPath
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /** @return DocbookPage[] */
    public function __invoke(string $docbookPath): array
    {
        $this->logger->debug(sprintf('[%s] Analysing path "%s" for markdown files', self::class, $docbookPath));

        $pages = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docbookPath)) as $file) {
            Assert::isInstanceOf($file, SplFileInfo::class);
            if ($file->isDir() || $file->getExtension() !== 'md') {
                continue;
            }

            $templateFilename = $file->getPathname();
            $slug             = $this->slugForFilename($docbookPath, $templateFilename);
            $content          = file_get_contents($templateFilename);

            $this->logger->debug(sprintf('[%s] Found Markdown file "%s", assigning slug "%s"', self::class, $templateFilename, $slug));

            $pages[] = DocbookPage::fromSlugAndContent(
                $templateFilename,
                $slug,
                $content,
            );
        }

        return $pages;
    }

    private function slugForFilename(string $docbookPath, string $templateFilename): string
    {
        $filenameWithoutBasePath = ltrim(str_replace($docbookPath, '', $templateFilename), '/');

        return str_replace(['.md', '/'], ['', '_'], $filenameWithoutBasePath);
    }
}
