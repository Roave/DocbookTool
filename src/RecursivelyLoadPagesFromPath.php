<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Roave\DocbookTool\DocbookPage;
use RuntimeException;
use SplFileInfo;

class RecursivelyLoadPagesFromPath
{
    /** @return DocbookPage[] */
    public function __invoke(string $docbookPath): array
    {
        $pages = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docbookPath)) as $file) {
            assert($file instanceof SplFileInfo);
            if ($file->isDir() || $file->getExtension() !== 'md') {
                continue;
            }

            $templateFilename = $file->getPathname();
            $slug = $this->slugForFilename($docbookPath, $templateFilename);
            $content = file_get_contents($templateFilename);
            $title = $this->titleForFile($templateFilename, $content);

            $pages[] = DocbookPage::fromSlugTitleAndContent(
                $slug,
                $content,
                $title
            );
        }

        return $pages;
    }

    private function slugForFilename(string $docbookPath, string $templateFilename): string
    {
        return str_replace([$docbookPath, '.md', '/'], ['', '', '__'], $templateFilename);
    }

    private function titleForFile(string $templateFilename, string $pageContent): string
    {
        $firstLine = strtok($pageContent, "\n");

        if (! str_starts_with($firstLine, '# ')) {
            throw new RuntimeException('First line of markdown file ' . $templateFilename . ' did not start with "# "...');
        }

        return str_replace('# ', '', $firstLine);
    }
}
