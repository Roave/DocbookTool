<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Client;

use CurlHandle;
use GuzzleHttp\Psr7\Response;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Webmozart\Assert\Assert;

use function Safe\curl_exec;
use function Safe\curl_init;
use function Safe\curl_setopt;

use const CURLOPT_PROTOCOLS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;
use const CURLPROTO_FILE;

final readonly class FileClient implements ClientInterface
{
    private CurlHandle $curlHandle;

    public function __construct()
    {
        $this->curlHandle = curl_init();
        curl_setopt($this->curlHandle, CURLOPT_PROTOCOLS, CURLPROTO_FILE);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
    }

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        Assert::same($request->getMethod(), 'GET');
        Assert::same($request->getUri()->getScheme(), 'file');
        Assert::same($request->getUri()->getHost(), '');

        curl_setopt($this->curlHandle, CURLOPT_URL, $request->getUri());
        $fileContent = curl_exec($this->curlHandle);
        Assert::string($fileContent);

        return new Response(200, [], $fileContent);
    }
}
