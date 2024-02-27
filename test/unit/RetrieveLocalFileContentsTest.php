<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Roave\DocbookTool\RetrieveLocalFileContents;
use RuntimeException;
use Safe\Exceptions\FilesystemException;

#[CoversClass(RetrieveLocalFileContents::class)]
final class RetrieveLocalFileContentsTest extends TestCase
{
    public function testRetrievesFileUsingRelativeDir(): void
    {
        self::assertSame("Lorem ipsum\n", (new RetrieveLocalFileContents())('local-file.txt', __DIR__ . '/../fixture'));
    }

    public function testRetrievesFileWithQueryAndFragment(): void
    {
        self::assertSame("Lorem ipsum\n", (new RetrieveLocalFileContents())('local-file.txt?a#foo', __DIR__ . '/../fixture'));
    }

    public function testNotExistingFilePathThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Could not retrieve file "not-existing\.txt" in directory ".*?"\.$/');
        (new RetrieveLocalFileContents())('not-existing.txt', __DIR__ . '/../fixture');
    }

    public function testInvalidWorkingDirectoryThrowsException(): void
    {
        $this->expectException(FilesystemException::class);
        (new RetrieveLocalFileContents())('not-existing.txt', __DIR__ . '/../not-existing');
    }
}
