<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use Webmozart\Assert\Assert;
use function Safe\file_get_contents;
use function str_replace;

class RecursivelyLoadPagesFromPath
{
    /** @return DocbookPage[] */
    public function __invoke(string $docbookPath): array
    {
        $pages = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docbookPath)) as $file) {
            Assert::isInstanceOf($file, SplFileInfo::class);
            if ($file->isDir() || $file->getExtension() !== 'md') {
                continue;
            }

            $templateFilename = $file->getPathname();
            $slug             = $this->slugForFilename($docbookPath, $templateFilename);
            $content          = file_get_contents($templateFilename);

            $pages[] = DocbookPage::fromSlugTitleAndContent(
                $slug,
                $content
            );
        }

        // Currently, sort everything alphabetically. Would be nice to control sort order though.
        usort(
            $pages,
            static function (DocbookPage $a, DocbookPage $b): int {
                return $a->slug() <=> $b->slug();
            }
        );

        return $pages;
    }

    private function slugForFilename(string $docbookPath, string $templateFilename): string
    {
        return str_replace([$docbookPath, '.md', '/'], ['', '', '__'], $templateFilename);
    }
}
