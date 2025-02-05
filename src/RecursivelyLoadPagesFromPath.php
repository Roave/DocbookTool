<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use Psl\Type;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function ltrim;
use function Psl\File\read;
use function Psl\invariant;
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

        $pages    = [];
        $notEmpty = Type\non_empty_string();

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docbookPath)) as $file) {
            invariant($file instanceof SplFileInfo, 'File should always be an ' . SplFileInfo::class);

            if ($file->isDir() || $file->getExtension() !== 'md') {
                continue;
            }

            $templateFilename = $notEmpty->coerce($file->getPathname());
            $slug             = $this->slugForFilename($docbookPath, $templateFilename);
            $content          = read($templateFilename);

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
