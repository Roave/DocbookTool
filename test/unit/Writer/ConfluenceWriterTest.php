<?php

declare(strict_types=1);

namespace Roave\DocbookToolUnitTest\Writer;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\Writer\ConfluenceWriter;
use Roave\DocbookToolUnitTest\TestLogger;

use function assert;
use function json_decode;
use function json_encode;
use function md5;
use function sprintf;
use function str_replace;
use function strlen;

use const JSON_THROW_ON_ERROR;

/** @covers \Roave\DocbookTool\Writer\ConfluenceWriter */
final class ConfluenceWriterTest extends TestCase
{
    // Indices for Guzzle transactions, as MockHandler only likes integer keys
    private const GET_HASH            = 0;
    private const GET_PAGE            = 1;
    private const GET_ATTACHMENTS     = 2;
    private const POST_ATTACHMENT_JPG = 3;
    private const POST_ATTACHMENT_PNG = 4;
    private const PUT_PAGE            = 5;
    private const POST_PUT_HASH       = 6;

    private TestLogger $testLogger;

    public function setUp(): void
    {
        parent::setUp();

        $this->testLogger = new TestLogger();
    }

    private function assertPostContentRequestWasCorrect(
        RequestInterface $postedContentRequest,
        int $confluencePageId,
        string $expectedContent,
        int $expectedVersion,
    ): void {
        self::assertSame('PUT', $postedContentRequest->getMethod());
        self::assertSame('https://fake-confluence-url/' . $confluencePageId, (string) $postedContentRequest->getUri());
        self::assertSame(
            [
                'id' => (string) $confluencePageId,
                'type' => 'page type',
                'title' => 'page title',
                'space' => ['key' => 'space key'],
                'body' => [
                    'storage' => [
                        'value' => <<<HTML
<p><strong style="color: #ff0000;">NOTE: This documentation is auto generated, do not edit this directly in Confluence, as your changes will be overwritten!</strong></p>$expectedContent
HTML,
                        'representation' => 'storage',
                    ],
                ],
                'version' => ['number' => $expectedVersion],
            ],
            json_decode((string) $postedContentRequest->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    private function assertPostAttatchmentRequestWasCorrect(
        RequestInterface $postedAttachmentRequest,
        int $confluencePageId,
        string $expectedContent,
        string $expectedMimeType,
        string $expectedFileExtension,
    ): void {
        self::assertSame('POST', $postedAttachmentRequest->getMethod());
        self::assertSame('https://fake-confluence-url/' . $confluencePageId . '/child/attachment', (string) $postedAttachmentRequest->getUri());
        $body = $postedAttachmentRequest->getBody();
        assert($body instanceof MultipartStream);
        $expectedHash          = md5($expectedContent);
        $expectedContentLength = strlen($expectedContent);
        self::assertSame(
            sprintf(
                <<<EXPECTED_BODY
--%s
Content-Disposition: form-data; name="file"; filename="$expectedHash.$expectedFileExtension"
Content-Length: $expectedContentLength
Content-Type: $expectedMimeType

$expectedContent
--%s--

EXPECTED_BODY,
                $body->getBoundary(),
                $body->getBoundary(),
            ),
            str_replace("\r\n", "\n", (string) $body),
        );
    }

    public function testConfluenceUpload(): void
    {
        $guzzleLog = [];

        $handlerStack = HandlerStack::create(new MockHandler([
            // GET /{pageId}/property/docbook-hash?expand=content,version
            self::GET_HASH => new Response(200, [], json_encode([
                'value' => 'different hash to force update',
                'version' => ['number' => '1'],
            ], JSON_THROW_ON_ERROR)),
            // GET /{pageId}
            self::GET_PAGE => new Response(200, [], json_encode([
                'id' => '123456789',
                'type' => 'page type',
                'title' => 'page title',
                'space' => ['key' => 'space key'],
                'version' => ['number' => '1'],
            ], JSON_THROW_ON_ERROR)),
            // GET /{pageId}/child/attachment
            self::GET_ATTACHMENTS => new Response(200, [], json_encode([
                'results' => [
                    ['title' => 'attachment'],
                ],
            ], JSON_THROW_ON_ERROR)),
            // POST /{pageId}/child/attachment
            self::POST_ATTACHMENT_JPG => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
            // POST /{pageId}/child/attachment
            self::POST_ATTACHMENT_PNG => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
            // PUT /{pageId}
            self::PUT_PAGE => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
            // POST|PUT /{pageId}/property/docbook-hash
            self::POST_PUT_HASH => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
        ]));
        $handlerStack->push(Middleware::history($guzzleLog));

        $confluence = new ConfluenceWriter(
            new Client(['handler' => $handlerStack]),
            'https://fake-confluence-url',
            'Something',
            $this->testLogger,
            false,
        );

        $confluence->__invoke([
            DocbookPage::fromSlugAndContent(
                'path',
                'page-slug',
                <<<'HTML'
<strong>Hello</strong>
<img src="data:image/jpg;base64,WW91IHdpbGwgZmluZCB0aGF0IEkgYW0gYSBKUEcsIG1ha2Ugbm8gbWlzdGFrZXM=" alt="a JPG" />
<img src="data:image/png;base64,SSBhbSBhIFBORyBob25lc3RseSBndXY=" alt="a PNG" />
HTML,
            )->withFrontMatter(['confluencePageId' => 123456789]),
        ]);

        /** @psalm-var array<self::*,array{request:RequestInterface}> $guzzleLog */

        $postedJpgAttachment = $guzzleLog[self::POST_ATTACHMENT_JPG]['request'];
        assert($postedJpgAttachment instanceof RequestInterface);
        $this->assertPostAttatchmentRequestWasCorrect(
            $postedJpgAttachment,
            123456789,
            'You will find that I am a JPG, make no mistakes',
            'image/jpeg',
            'jpg',
        );

        $postedPngAttachment = $guzzleLog[self::POST_ATTACHMENT_PNG]['request'];
        assert($postedPngAttachment instanceof RequestInterface);
        $this->assertPostAttatchmentRequestWasCorrect(
            $postedPngAttachment,
            123456789,
            'I am a PNG honestly guv',
            'image/png',
            'png',
        );

        $postedPageContent = $guzzleLog[self::PUT_PAGE]['request'];
        assert($postedPageContent instanceof RequestInterface);
        $this->assertPostContentRequestWasCorrect(
            $postedPageContent,
            123456789,
            <<<'HTML'
<strong>Hello</strong>
<ac:image><ri:attachment ri:filename="9b808ef712db49f2a0cc5e6e0dd7758e.jpg" /></ac:image>
<ac:image><ri:attachment ri:filename="b3b5b79f3d9b7144e6046bb148bccad5.png" /></ac:image>
HTML,
            2,
        );

        self::assertContains(
            sprintf('[%s] - OK! Successfully updated confluence page 123456789 with page-slug ...', ConfluenceWriter::class),
            $this->testLogger->logMessages,
        );
    }

    public function testConfluenceUploadSkippingHashChecks(): void
    {
        $guzzleLog = [];

        $handlerStack = HandlerStack::create(new MockHandler([
            // GET /{pageId}
            0 => new Response(200, [], json_encode([
                'id' => '123456789',
                'type' => 'page type',
                'title' => 'page title',
                'space' => ['key' => 'space key'],
                'version' => ['number' => '1'],
            ], JSON_THROW_ON_ERROR)),
            // GET /{pageId}/child/attachment
            1 => new Response(200, [], json_encode([
                'results' => [
                    ['title' => 'attachment'],
                ],
            ], JSON_THROW_ON_ERROR)),
            // PUT /{pageId}
            2 => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
        ]));
        $handlerStack->push(Middleware::history($guzzleLog));

        $confluence = new ConfluenceWriter(
            new Client(['handler' => $handlerStack]),
            'https://fake-confluence-url',
            'Something',
            $this->testLogger,
            true,
        );

        $confluence->__invoke([
            DocbookPage::fromSlugAndContent(
                'path',
                'page-slug',
                <<<'HTML'
<strong>Hello</strong>
HTML,
            )->withFrontMatter(['confluencePageId' => 123456789]),
        ]);

        /** @psalm-var array<self::*,array{request:RequestInterface}> $guzzleLog */

        $postedPageContent = $guzzleLog[2]['request'];
        assert($postedPageContent instanceof RequestInterface);
        $this->assertPostContentRequestWasCorrect(
            $postedPageContent,
            123456789,
            <<<'HTML'
<strong>Hello</strong>
HTML,
            2,
        );

        self::assertContains(
            sprintf('[%s] - OK! Successfully updated confluence page 123456789 with page-slug ...', ConfluenceWriter::class),
            $this->testLogger->logMessages,
        );
    }

    public function testConfluenceUploadWithLinksToOtherPages(): void
    {
        $guzzleLog = [];

        $handlerStack = HandlerStack::create(new MockHandler([
            // GET /{pageId}
            0 => new Response(200, [], json_encode([
                'id' => '111111111',
                'type' => 'page type',
                'title' => 'page title',
                'space' => ['key' => 'space key'],
                'version' => ['number' => '1'],
            ], JSON_THROW_ON_ERROR)),
            // GET /{pageId}/child/attachment
            1 => new Response(200, [], json_encode([
                'results' => [
                    ['title' => 'attachment'],
                ],
            ], JSON_THROW_ON_ERROR)),
            // PUT /{pageId}
            2 => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
            // GET /{pageId}
            3 => new Response(200, [], json_encode([
                'id' => '222222222',
                'type' => 'page type',
                'title' => 'page title',
                'space' => ['key' => 'space key'],
                'version' => ['number' => '1'],
            ], JSON_THROW_ON_ERROR)),
            // GET /{pageId}/child/attachment
            4 => new Response(200, [], json_encode([
                'results' => [
                    ['title' => 'attachment'],
                ],
            ], JSON_THROW_ON_ERROR)),
            // PUT /{pageId}
            5 => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
        ]));
        $handlerStack->push(Middleware::history($guzzleLog));

        $confluence = new ConfluenceWriter(
            new Client(['handler' => $handlerStack]),
            'https://fake-confluence-url',
            'Something',
            $this->testLogger,
            true,
        );

        $confluence->__invoke([
            DocbookPage::fromSlugAndContent(
                '/path/to/page1.md',
                'page1-slug',
                <<<'HTML'
<a href="./page2.md">page2</a>
HTML,
            )->withFrontMatter(['confluencePageId' => 111111111]),
            DocbookPage::fromSlugAndContent(
                '/path/to/page2.md',
                'page2-slug',
                <<<'HTML'
<a href="page1.md">page1</a>
HTML,
            )->withFrontMatter(['confluencePageId' => 222222222]),
        ]);

        /** @psalm-var array<self::*,array{request:RequestInterface}> $guzzleLog */

        $postedPageContent = $guzzleLog[2]['request'];
        assert($postedPageContent instanceof RequestInterface);
        $this->assertPostContentRequestWasCorrect(
            $postedPageContent,
            111111111,
            <<<'HTML'
<a href="https://fake-confluence-url/pages/viewpage.action?pageId=222222222">page2</a>
HTML,
            2,
        );

        $postedPageContent = $guzzleLog[5]['request'];
        assert($postedPageContent instanceof RequestInterface);
        $this->assertPostContentRequestWasCorrect(
            $postedPageContent,
            222222222,
            <<<'HTML'
<a href="https://fake-confluence-url/pages/viewpage.action?pageId=111111111">page1</a>
HTML,
            2,
        );

        self::assertContains(
            sprintf('[%s] - OK! Successfully updated confluence page 111111111 with page1-slug ...', ConfluenceWriter::class),
            $this->testLogger->logMessages,
        );
        self::assertContains(
            sprintf('[%s] - OK! Successfully updated confluence page 222222222 with page2-slug ...', ConfluenceWriter::class),
            $this->testLogger->logMessages,
        );
    }
}
