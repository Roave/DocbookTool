<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use RuntimeException;

use function array_key_exists;
use function is_bool;
use function is_int;
use function is_string;
use function str_ends_with;
use function str_starts_with;
use function strip_tags;
use function strtok;

/** @psalm-immutable */
class DocbookPage
{
    /** @param array<array-key, mixed> $frontMatter */
    private function __construct(
        private string $slug,
        private string $content,
        private array $frontMatter
    ) {
    }

    public static function fromSlugTitleAndContent(string $slug, string $content): self
    {
        return new self($slug, $content, []);
    }

    public function withReplacedContent(string $newContent): self
    {
        $new = clone $this;

        $new->content = $newContent;

        return $new;
    }

    /** @param array<array-key, mixed> $frontMatter */
    public function withFrontMatter(array $frontMatter): self
    {
        $new = clone $this;

        $new->frontMatter = $frontMatter;

        return $new;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function content(): string
    {
        return $this->content;
    }

    private function determineTitleFromContent(): string
    {
        $firstLine = strtok($this->content, "\n");

        if (! str_starts_with($firstLine, '<h1>') || ! str_ends_with($firstLine, '</h1>')) {
            throw new RuntimeException('First line of markdown file ' . $this->slug() . ' did not start with "# "...');
        }

        return strip_tags($firstLine);
    }

    public function title(): string
    {
        if (array_key_exists('title', $this->frontMatter) && is_string($this->frontMatter['title'])) {
            return $this->frontMatter['title'];
        }

        return $this->determineTitleFromContent();
    }

    public function shouldGeneratePdf(): bool
    {
        return array_key_exists('pdf', $this->frontMatter)
            && is_bool($this->frontMatter['pdf'])
            && $this->frontMatter['pdf'];
    }

    public function confluencePageId(): ?int
    {
        if (
            ! array_key_exists('confluencePageId', $this->frontMatter)
            || ! is_int($this->frontMatter['confluencePageId'])
        ) {
            return null;
        }

        return $this->frontMatter['confluencePageId'];
    }
}
