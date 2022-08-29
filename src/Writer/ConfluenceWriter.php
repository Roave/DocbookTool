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
use function array_merge;
use function hash_equals;
use function in_array;
use function md5;
use function preg_replace_callback;
use function Safe\base64_decode;
use function Safe\json_decode;
use function Safe\json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/** @psalm-type ListOfExtractedImageData = list<array{hash: string, data: string}> */
final class ConfluenceWriter implements OutputWriter
{
    private const CONFLUENCE_HEADER = '<p><strong style="color: #ff0000;">NOTE: This documentation is auto generated, do not edit this directly in Confluence, as your changes will be overwritten!</strong></p>';

    public function __construct(
        private ClientInterface $client,
        private string $confluenceContentApiUrl,
        private string $authHeader,
        private LoggerInterface $logger,
    ) {
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
        foreach ($docbookPages as $page) {
            if ($page->confluencePageId() === null) {
                continue;
            }

            $confluencePageId = $page->confluencePageId();

            $this->logger->info(sprintf(
                'Updating confluence page %s with %s ...',
                $confluencePageId,
                $page->slug(),
            ));

            [$confluenceContent, $imageData] = $this->extractImagesFromContent(
                self::CONFLUENCE_HEADER . $page->content(),
            );

            $latestContentHash = md5($confluenceContent);

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

                $hashUpdateMethod = 'POST';
                $confluenceHash   = '';
                $propertyVersion  = 0;
            }

            if (hash_equals($latestContentHash, $confluenceHash)) {
                $this->logger->info(sprintf(' - skipping %s, already up to date.', $page->slug()));
                continue;
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
                if (in_array($image['hash'] . '.png', $uploadedImages, true)) {
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
                                'filename' => $image['hash'] . '.png',
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
    }

    /** @psalm-return array{0:string, 1:ListOfExtractedImageData} */
    private function extractImagesFromContent(string $renderedContent): array
    {
        $images = [];

        $replacedContent = (string) preg_replace_callback(
            '/<img src="data:image\/png;base64,([a-zA-Z0-9=+\/]+)" alt="Diagram" \/>/',
            static function (array $m) use (&$images): string {
                /** @var array{1: string} $m */
                $imageBinaryData = base64_decode($m[1]);
                $imageHash       = md5($imageBinaryData);
                /** @psalm-var ListOfExtractedImageData $images */
                $images[] = [
                    'hash' => $imageHash,
                    'data' => $imageBinaryData,
                ];

                return '<ac:image><ri:attachment ri:filename="' . $imageHash . '.png" /></ac:image>';
            },
            $renderedContent,
        );

        /** @psalm-var ListOfExtractedImageData $images */
        return [$replacedContent, $images];
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
