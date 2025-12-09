<?php

declare(strict_types=1);

namespace Roave\DocbookTool;

use CurlHandle;
use Override;
use Psl\Type;
use RuntimeException;

use function curl_exec;
use function curl_init;
use function curl_setopt;
use function is_string;
use function Psl\Filesystem\canonicalize;
use function sprintf;

use const CURLOPT_PROTOCOLS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;
use const CURLPROTO_FILE;

final readonly class RetrieveLocalFileContents implements RetrieveFileContents
{
    private CurlHandle $curlHandle;

    public function __construct()
    {
        $this->curlHandle = Type\instance_of(CurlHandle::class)
            ->coerce(curl_init());

        curl_setopt($this->curlHandle, CURLOPT_PROTOCOLS, CURLPROTO_FILE);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
    }

    #[Override]
    public function __invoke(string $filePath, string $workingDirectory): string
    {
        $fileUri = 'file://' . (string) canonicalize($workingDirectory) . '/' . $filePath;

        curl_setopt($this->curlHandle, CURLOPT_URL, $fileUri);
        $fileContent = curl_exec($this->curlHandle);

        if (! is_string($fileContent)) {
            throw new RuntimeException(sprintf('Could not retrieve file "%s" in directory "%s".', $filePath, $workingDirectory));
        }

        return $fileContent;
    }
}
