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
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Roave\DocbookTool\DocbookPage;
use Roave\DocbookTool\Writer\ConfluenceWriter;
use Stringable;

use function assert;
use function json_decode;
use function json_encode;
use function sprintf;
use function str_replace;

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

    public function testConfluenceUpload(): void
    {
        $testLogger = new class implements LoggerInterface {
            /** @var list<string> */
            public array $logMessages = [];
            use LoggerTrait;

            /** @param array<array-key,mixed> $context */
            public function log(mixed $level, Stringable|string $message, array $context = []): void
            {
                $this->logMessages[] = (string) $message;
            }
        };

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
            $testLogger,
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
        self::assertSame('POST', $postedJpgAttachment->getMethod());
        self::assertSame('https://fake-confluence-url/123456789/child/attachment', (string) $postedJpgAttachment->getUri());
        $body = $postedJpgAttachment->getBody();
        assert($body instanceof MultipartStream);
        self::assertSame(
            sprintf(
                <<<'EXPECTED_BODY'
--%s
Content-Disposition: form-data; name="file"; filename="9b808ef712db49f2a0cc5e6e0dd7758e.jpg"
Content-Length: 47
Content-Type: image/jpeg

You will find that I am a JPG, make no mistakes
--%s--

EXPECTED_BODY,
                $body->getBoundary(),
                $body->getBoundary(),
            ),
            str_replace("\r\n", "\n", (string) $body),
        );

        $postedPngAttachment = $guzzleLog[self::POST_ATTACHMENT_PNG]['request'];
        assert($postedPngAttachment instanceof RequestInterface);
        self::assertSame('POST', $postedPngAttachment->getMethod());
        self::assertSame('https://fake-confluence-url/123456789/child/attachment', (string) $postedPngAttachment->getUri());
        $body = $postedPngAttachment->getBody();
        assert($body instanceof MultipartStream);
        self::assertSame(
            sprintf(
                <<<'EXPECTED_BODY'
--%s
Content-Disposition: form-data; name="file"; filename="b3b5b79f3d9b7144e6046bb148bccad5.png"
Content-Length: 23
Content-Type: image/png

I am a PNG honestly guv
--%s--

EXPECTED_BODY,
                $body->getBoundary(),
                $body->getBoundary(),
            ),
            str_replace("\r\n", "\n", (string) $body),
        );

        $postedPageContent = $guzzleLog[self::PUT_PAGE]['request'];
        assert($postedPageContent instanceof RequestInterface);
        self::assertSame('PUT', $postedPageContent->getMethod());
        self::assertSame('https://fake-confluence-url/123456789', (string) $postedPageContent->getUri());
        self::assertSame(
            [
                'id' => '123456789',
                'type' => 'page type',
                'title' => 'page title',
                'space' => ['key' => 'space key'],
                'body' => [
                    'storage' => [
                        'value' => <<<'HTML'
<p><strong style="color: #ff0000;">NOTE: This documentation is auto generated, do not edit this directly in Confluence, as your changes will be overwritten!</strong></p><strong>Hello</strong>
<ac:image><ri:attachment ri:filename="9b808ef712db49f2a0cc5e6e0dd7758e.jpg" /></ac:image>
<ac:image><ri:attachment ri:filename="b3b5b79f3d9b7144e6046bb148bccad5.png" /></ac:image>
HTML,
                        'representation' => 'storage',
                    ],
                ],
                'version' => ['number' => 2],
            ],
            json_decode((string) $postedPageContent->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );

        self::assertContains(
            sprintf('[%s] - OK! Successfully updated confluence page 123456789 with page-slug ...', ConfluenceWriter::class),
            $testLogger->logMessages,
        );
    }

    public function testConfluenceUploadSkippingHashChecks(): void
    {
        $testLogger = new class implements LoggerInterface {
            /** @var list<string> */
            public array $logMessages = [];
            use LoggerTrait;

            /** @param array<array-key,mixed> $context */
            public function log(mixed $level, Stringable|string $message, array $context = []): void
            {
                $this->logMessages[] = (string) $message;
            }
        };

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
            // POST /{pageId}/child/attachment
            2 => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
            // POST /{pageId}/child/attachment
            3 => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
            // PUT /{pageId}
            4 => new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
        ]));
        $handlerStack->push(Middleware::history($guzzleLog));

        $confluence = new ConfluenceWriter(
            new Client(['handler' => $handlerStack]),
            'https://fake-confluence-url',
            'Something',
            $testLogger,
            true,
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

        $postedJpgAttachment = $guzzleLog[2]['request'];
        assert($postedJpgAttachment instanceof RequestInterface);
        self::assertSame('POST', $postedJpgAttachment->getMethod());
        self::assertSame('https://fake-confluence-url/123456789/child/attachment', (string) $postedJpgAttachment->getUri());
        $body = $postedJpgAttachment->getBody();
        assert($body instanceof MultipartStream);
        self::assertSame(
            sprintf(
                <<<'EXPECTED_BODY'
--%s
Content-Disposition: form-data; name="file"; filename="9b808ef712db49f2a0cc5e6e0dd7758e.jpg"
Content-Length: 47
Content-Type: image/jpeg

You will find that I am a JPG, make no mistakes
--%s--

EXPECTED_BODY,
                $body->getBoundary(),
                $body->getBoundary(),
            ),
            str_replace("\r\n", "\n", (string) $body),
        );

        $postedPngAttachment = $guzzleLog[3]['request'];
        assert($postedPngAttachment instanceof RequestInterface);
        self::assertSame('POST', $postedPngAttachment->getMethod());
        self::assertSame('https://fake-confluence-url/123456789/child/attachment', (string) $postedPngAttachment->getUri());
        $body = $postedPngAttachment->getBody();
        assert($body instanceof MultipartStream);
        self::assertSame(
            sprintf(
                <<<'EXPECTED_BODY'
--%s
Content-Disposition: form-data; name="file"; filename="b3b5b79f3d9b7144e6046bb148bccad5.png"
Content-Length: 23
Content-Type: image/png

I am a PNG honestly guv
--%s--

EXPECTED_BODY,
                $body->getBoundary(),
                $body->getBoundary(),
            ),
            str_replace("\r\n", "\n", (string) $body),
        );

        $postedPageContent = $guzzleLog[4]['request'];
        assert($postedPageContent instanceof RequestInterface);
        self::assertSame('PUT', $postedPageContent->getMethod());
        self::assertSame('https://fake-confluence-url/123456789', (string) $postedPageContent->getUri());
        self::assertSame(
            [
                'id' => '123456789',
                'type' => 'page type',
                'title' => 'page title',
                'space' => ['key' => 'space key'],
                'body' => [
                    'storage' => [
                        'value' => <<<'HTML'
<p><strong style="color: #ff0000;">NOTE: This documentation is auto generated, do not edit this directly in Confluence, as your changes will be overwritten!</strong></p><strong>Hello</strong>
<ac:image><ri:attachment ri:filename="9b808ef712db49f2a0cc5e6e0dd7758e.jpg" /></ac:image>
<ac:image><ri:attachment ri:filename="b3b5b79f3d9b7144e6046bb148bccad5.png" /></ac:image>
HTML,
                        'representation' => 'storage',
                    ],
                ],
                'version' => ['number' => 2],
            ],
            json_decode((string) $postedPageContent->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );

        self::assertContains(
            sprintf('[%s] - OK! Successfully updated confluence page 123456789 with page-slug ...', ConfluenceWriter::class),
            $testLogger->logMessages,
        );
    }
}
