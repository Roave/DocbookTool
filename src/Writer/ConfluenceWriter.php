<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Writer;

use DOMDocument;
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

            $confluenceDomContent = new DOMDocument();
            $confluenceDomContent->loadHTML(
                self::CONFLUENCE_HEADER . $page->content(),
                \LIBXML_HTML_NODEFDTD | \LIBXML_HTML_NOIMPLIED
            );

            $imageData = $this->extractImagesFromContent($confluenceDomContent);

            // @todo ADD THIS BACK IN
//            $confluenceDomContent = $this->replaceLocalMarkdownLinks($page, $mapPathsToConfluencePageIds, $confluenceDomContent);

            $confluenceContent = $confluenceDomContent->saveHTML();

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

    /** @psalm-return ListOfExtractedImageData */
    private function extractImagesFromContent(DOMDocument &$renderedContent): array
    {
        $images = [];

        /** @var list<array{old:\DOMElement,new:\DOMElement}> $nodeReplacements */
        $nodeReplacements = [];

        foreach ($renderedContent->getElementsByTagName('img') as $img) {
            /** @var \DOMNode $img */
            $srcAttributeValue = $img->getAttribute('src');

            Assert::stringNotEmpty($srcAttributeValue);
            if (!preg_match('#data:([^;]+);base64,([a-zA-Z0-9=+\/]+)#', $srcAttributeValue, $dataUrlParts)) {
                continue;
            }

            /** @var array{1: string, 2: string} $dataUrlParts */
            $imageBinaryData   = base64_decode($dataUrlParts[2]);
            $imageHashFilename = md5($imageBinaryData) . '.' . match ($dataUrlParts[1]) {
                'image/png' => 'png',
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/gif' => 'gif',
            };

            $images[] = [
                'hashFilename' => $imageHashFilename,
                'data' => $imageBinaryData,
            ];
            $riFilename = $renderedContent->createAttribute('ri:filename');
            $riFilename->value = $imageHashFilename;
            $riAttachment = $renderedContent->createElement('ri:attachment');
            $riAttachment->appendChild($riFilename);
            $acImage = $renderedContent->createElement('ac:image');
            $acImage->appendChild($riAttachment);

            $nodeReplacements[] = ['old' => $img, 'new' => $acImage];
            //'<ac:image><ri:attachment ri:filename="' . $imageHashFilename . '" /></ac:image>'
        }

        foreach ($nodeReplacements as $replacementSet) {
            $replacementSet['old']->parentNode->replaceChild($replacementSet['new'], $replacementSet['old']);
        }

        /** @psalm-var ListOfExtractedImageData $images */
        return $images;
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
