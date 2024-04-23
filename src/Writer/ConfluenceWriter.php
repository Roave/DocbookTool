<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use JsonException;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use Safe\Exceptions\SafeExceptionInterface;
use Webmozart\Assert\Assert;

use function array_column;
use function array_key_exists;
use function array_merge;
use function dirname;
use function hash_equals;
use function html_entity_decode;
use function in_array;
use function md5;
use function preg_replace_callback;
use function realpath;
use function Safe\base64_decode;
use function Safe\json_decode;
use function Safe\json_encode;
use function sprintf;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

/** @psalm-type ListOfExtractedImageData = list<array{hashFilename: string, data: string}> */
final class ConfluenceWriter implements OutputWriter
{
    /** @link https://confluence.atlassian.com/doc/code-block-macro-139390.html */
    private const ALLOWED_CONFLUENCE_CODE_FORMATS = [
        'actionscript',
        'applescript',
        'bash',
        'csharp',
        'coldfusion',
        'cpp',
        'css',
        'delphi',
        'diff',
        'erlang',
        'groovy',
        'html',
        'java',
        'javafx',
        'javascript',
        'none',
        'perl',
        'php',
        'powershell',
        'python',
        'ruby',
        'sass',
        'scala',
        'sql',
        'xml',
        'vb',
        'yaml',
    ];

    private const CONFLUENCE_HEADER = '<p><strong style="color: #ff0000;">NOTE: This documentation is auto generated, do not edit this directly in Confluence, as your changes will be overwritten!</strong></p>';

    private readonly string $confluenceContentApiUrl;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $confluenceContentBaseUrl,
        private readonly string $authHeader,
        private readonly LoggerInterface $logger,
        private readonly bool $skipContentHashChecks,
    ) {
        $this->confluenceContentApiUrl = $this->confluenceContentBaseUrl . '/rest/api/content';
    }

    /**
     * @param DocbookPage[] $docbookPages
     *
     * @throws GuzzleException
     * @throws JsonException
     * @throws SafeExceptionInterface
     */
    public function __invoke(array $docbookPages): void
    {
        /** @var array<string, int> $mapPathsToConfluencePageIds */
        $mapPathsToConfluencePageIds = [];
        foreach ($docbookPages as $page) {
            if ($page->confluencePageId() === null) {
                continue;
            }

            $mapPathsToConfluencePageIds[$page->path()] = $page->confluencePageId();
        }

        foreach ($docbookPages as $page) {
            if ($page->confluencePageId() === null) {
                continue;
            }

            $confluencePageId = $page->confluencePageId();

            $this->logger->info(sprintf(
                '[%s] Updating confluence page %s with %s ...',
                self::class,
                $confluencePageId,
                $page->slug(),
            ));

            [$confluenceContent, $imageData] = $this->extractImagesFromContent(
                self::CONFLUENCE_HEADER . $page->content(),
            );

            $confluenceContent = $this->replaceLocalMarkdownLinks($page, $mapPathsToConfluencePageIds, $confluenceContent);
            $confluenceContent = $this->replaceCodeBlocks($confluenceContent);

            $hashUpdateMethod  = 'POST';
            $latestContentHash = md5($confluenceContent);
            $propertyVersion   = 0;

            if (! $this->skipContentHashChecks) {
                try {
                    /**
                     * @psalm-var array{
                     *   value: string,
                     *   version: array{
                     *     number: string,
                     *   },
                     * } $confluenceHashResponse
                     */
                    $confluenceHashResponse = $this->confluenceRequest(
                        'GET',
                        $confluencePageId . '/property/docbook-hash?expand=content,version',
                        null,
                    );

                    $hashUpdateMethod = 'PUT';
                    $confluenceHash   = $confluenceHashResponse['value'];
                    $propertyVersion  = (int) $confluenceHashResponse['version']['number'];
                } catch (ClientException $exception) {
                    if ($exception->getResponse()->getStatusCode() !== 404) {
                        throw $exception;
                    }

                    $confluenceHash = '';
                }

                if (hash_equals($latestContentHash, $confluenceHash)) {
                    $this->logger->info(sprintf('[%s] - skipping %s, already up to date.', self::class, $page->slug()));
                    continue;
                }
            }

            /**
             * @psalm-var array{
             *   id: string,
             *   type: string,
             *   title: string,
             *   space: array{
             *     key: string,
             *   },
             *   version: array{
             *     number: string,
             *   },
             * } $currentPageResponse
             */
            $currentPageResponse = $this->confluenceRequest(
                'GET',
                (string) $confluencePageId,
                null,
            );

            /**
             * @psalm-var array{
             *   results: list<array{
             *     title: string
             *   }>
             * }
             */
            $currentPageAttachments = $this->confluenceRequest(
                'GET',
                $confluencePageId . '/child/attachment',
                null,
            );

            $uploadedImages = array_column($currentPageAttachments['results'], 'title');

            foreach ($imageData as $image) {
                if (in_array($image['hashFilename'], $uploadedImages, true)) {
                    continue;
                }

                /** @noinspection UnusedFunctionResultInspection */
                $this->confluenceRequest(
                    'POST',
                    $confluencePageId . '/child/attachment',
                    null,
                    ['X-Atlassian-Token' => 'nocheck'],
                    [
                        'multipart' => [
                            [
                                'name' => 'file',
                                'contents' => $image['data'],
                                'filename' => $image['hashFilename'],
                            ],
                        ],
                    ],
                );
            }

            /** @noinspection UnusedFunctionResultInspection */
            $this->confluenceRequest(
                'PUT',
                (string) $confluencePageId,
                [
                    'id' => $currentPageResponse['id'],
                    'type' => $currentPageResponse['type'],
                    'title' => $currentPageResponse['title'],
                    'space' => [
                        'key' => $currentPageResponse['space']['key'],
                    ],
                    'body' => [
                        'storage' => [
                            'value' => $confluenceContent,
                            'representation' => 'storage',
                        ],
                    ],
                    'version' => [
                        'number' => (int) $currentPageResponse['version']['number'] + 1,
                    ],
                ],
            );

            if (! $this->skipContentHashChecks) {
                /** @noinspection UnusedFunctionResultInspection */
                $this->confluenceRequest(
                    $hashUpdateMethod,
                    $confluencePageId . '/property/docbook-hash',
                    [
                        'key' => 'docbook-hash',
                        'value' => $latestContentHash,
                        'version' => [
                            'number' => $propertyVersion + 1,
                        ],
                    ],
                );
            }

            $this->logger->debug(sprintf(
                '[%s] - OK! Successfully updated confluence page %s with %s ...',
                self::class,
                $confluencePageId,
                $page->slug(),
            ));
        }
    }

    /** @psalm-return array{0:string, 1:ListOfExtractedImageData} */
    private function extractImagesFromContent(string $renderedContent): array
    {
        $images = [];

        $replacedContent = (string) preg_replace_callback(
            '/<img src="data:([^;]+);base64,([a-zA-Z0-9=+\/]+)" alt="([^\"]+)" \/>/',
            static function (array $m) use (&$images): string {
                /** @var array{1: string, 2: string, 3: string} $m */
                $imageBinaryData   = base64_decode($m[2]);
                $imageHashFilename = md5($imageBinaryData) . '.' . match ($m[1]) {
                    'image/png' => 'png',
                    'image/jpeg', 'image/jpg' => 'jpg',
                    'image/gif' => 'gif',
                };

                $images[] = [
                    'hashFilename' => $imageHashFilename,
                    'data' => $imageBinaryData,
                ];

                return '<ac:image><ri:attachment ri:filename="' . $imageHashFilename . '" /></ac:image>';
            },
            $renderedContent,
        );

        /** @psalm-var ListOfExtractedImageData $images */
        return [$replacedContent, $images];
    }

    /** @param array<string, int> $mapPathsToConfluencePageIds */
    private function replaceLocalMarkdownLinks(DocbookPage $page, array $mapPathsToConfluencePageIds, string $renderedContent): string
    {
        $currentPagePath = dirname($page->path());

        return (string) preg_replace_callback(
            '/<a href="([^\"]+)">/',
            function (array $m) use ($currentPagePath, $mapPathsToConfluencePageIds): string {
                /** @var array{1: string} $m */
                $fullPath = realpath($currentPagePath . DIRECTORY_SEPARATOR . $m[1]);

                if ($fullPath === false || ! array_key_exists($fullPath, $mapPathsToConfluencePageIds)) {
                    return '<a href="' . $m[1] . '">';
                }

                return '<a href="' . $this->confluenceContentBaseUrl . '/pages/viewpage.action?pageId=' . $mapPathsToConfluencePageIds[$fullPath] . '">';
            },
            $renderedContent,
        );
    }

    private function replaceCodeBlocks(string $renderedContent): string
    {
        return (string) preg_replace_callback(
            '/<pre><code(?: class="lang-([^"]+)"|)>([^<]+)<\/code><\/pre>/',
            static function (array $m): string {
                /** @var array{1: string, 2: string} $m */
                $confluenceCodeLanguage = match ($m[1]) {
                    'json', 'js' => 'javascript',
                    'shell' => 'bash',
                    default => $m[1]
                };

                if (! in_array($confluenceCodeLanguage, self::ALLOWED_CONFLUENCE_CODE_FORMATS, true)) {
                    $confluenceCodeLanguage = 'none';
                }

                return sprintf(
                    <<<'XML'
<ac:structured-macro ac:name="code" ac:schema-version="1">
  <ac:parameter ac:name="language">%s</ac:parameter>
  <ac:plain-text-body><![CDATA[%s]]>
  </ac:plain-text-body>
</ac:structured-macro>
XML,
                    $confluenceCodeLanguage,
                    html_entity_decode($m[2]), // Since this is rendered in CDATA, we should not escape HTML entities
                );
            },
            $renderedContent,
        );
    }

    /**
     * @param array<array-key, mixed>|null                                                        $bodyContent
     * @param array<string, string>                                                               $overrideHeaders
     * @param array<string, string|list<array{name: string, contents: string, filename: string}>> $guzzleOptions
     *
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     * @throws GuzzleException
     */
    private function confluenceRequest(
        string $method,
        string $endpoint,
        array|null $bodyContent,
        array $overrideHeaders = [],
        array $guzzleOptions = [],
    ): array {
        $headers = [
            'Authorization' => $this->authHeader,
        ];

        if ($bodyContent !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        try {
            $stringResponse = $this->client
                ->send(
                    new Request(
                        $method,
                        $this->confluenceContentApiUrl . '/' . $endpoint,
                        array_merge($headers, $overrideHeaders),
                        $bodyContent !== null ? json_encode($bodyContent, JSON_THROW_ON_ERROR) : null,
                    ),
                    $guzzleOptions,
                )
                ->getBody()
                ->__toString();
        } catch (BadResponseException $responseException) {
            if (in_array($method, ['PUT', 'POST'])) {
                echo "Client exception, full response: \n";
                echo $responseException->getResponse()->getBody()->__toString();
                echo "\n\n";
            }

            throw $responseException;
        }

        if ($stringResponse === '') {
            return [];
        }

        try {
            $decodedResponse = json_decode(
                $stringResponse,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            Assert::isArray($decodedResponse);

            return $decodedResponse;
        } catch (JsonException $jsonException) {
            echo "Failed to JSON decode response: \n";
            echo $stringResponse;
            echo "\n\n";

            throw $jsonException;
        }
    }
}
