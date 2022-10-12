<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use RuntimeException;

use function base64_encode;
use function escapeshellcmd;
use function exec;
use function implode;
use function md5;
use function preg_replace;
use function preg_replace_callback;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\realpath;
use function Safe\unlink;
use function sprintf;
use function substr;
use function sys_get_temp_dir;

final class RenderPlantUmlDiagramInline implements PageFormatter
{
    /** Note: this is added by the `Dockerfile` build, it no longer exists in the repo itself */
    private const PLANTUML_JAR = __DIR__ . '/../../bin/plantuml.jar';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /** @throws RuntimeException */
    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Checking if PlantUML diagrams can be rendered and inlined in %s', self::class, $page->slug()));

        return $page->withReplacedContent(
            preg_replace_callback(
                '/```puml([\w\W]*?)```/',
                function (array $m) use ($page) {
                    /** @var array{1: string} $m */
                    $match = $m[1];

                    $this->logger->debug(sprintf('[%s] Found PlantUML diagram to render in %s', self::class, $page->slug()));

                    // fix any "@startuml filename" first lines to omit the filename
                    $match = preg_replace('/^(\s*@startuml)(\s.*)$/m', '\\1', $match, count: $startUmls);

                    if ($startUmls === 0) {
                        throw new RuntimeException(sprintf(
                            'Ensure the PUML in %s starts with @startuml and ends with @enduml',
                            $page->slug(),
                        ));
                    }

                    $contentHash  = md5($match);
                    $pumlFilename = sys_get_temp_dir() . '/' . $contentHash . '.puml';
                    $pngFilename  = sys_get_temp_dir() . '/' . $contentHash . '.png';
                    file_put_contents($pumlFilename, $match);

                    $this->logger->debug(sprintf('[%s] Using %s to render a PlantUML diagram in %s...', self::class, realpath(self::PLANTUML_JAR), $page->slug()));

                    /** @psalm-suppress ForbiddenCode */
                    exec(
                        escapeshellcmd('java -jar ' . self::PLANTUML_JAR . ' ' . $pumlFilename) . ' 2>&1',
                        $output,
                        $exitCode,
                    );

                    if ($exitCode !== 0) {
                        /** @psalm-var list<string> $output */
                        throw new RuntimeException(sprintf(
                            'Failed to render PUML in %s - starts "%s". Output was: %s',
                            $page->slug(),
                            substr($match, 0, 15),
                            implode("\n", $output),
                        ));
                    }

                    $this->logger->debug(sprintf('[%s] PlantUML diagram render complete %s', self::class, $page->slug()));

                    $pngContent = base64_encode(file_get_contents($pngFilename));
                    unlink($pumlFilename);
                    unlink($pngFilename);

                    return '![Diagram](data:image/png;base64,' . $pngContent . ')';
                },
                $page->content(),
            ),
        );
    }
}
