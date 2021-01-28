<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

/** @psalm-immutable */
class DocbookPage
{
    private function __construct(
        private string $slug,
        private string $content,
        private string $title,
        private bool $shouldGeneratePdf,
        private ?int $confluencePageId
    ) {
    }

    public static function fromSlugTitleAndContent(string $slug, string $title, string $content): self
    {
        return new self($slug, $title, $content, true, null);
    }

    public function withReplacedContent(string $newContent): self
    {
        $new          = clone $this;
        $new->content = $newContent;

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

    public function title(): string
    {
        return $this->title;
    }

    public function shouldGeneratePdf(): bool
    {
        return $this->shouldGeneratePdf;
    }

    public function confluencePageId(): ?int
    {
        return $this->confluencePageId;
    }
}
