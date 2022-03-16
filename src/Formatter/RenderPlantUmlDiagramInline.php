<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Roave\DocbookTool\DocbookPage;
use RuntimeException;

use function base64_encode;
use function escapeshellcmd;
use function exec;
use function implode;
use function md5;
use function preg_replace_callback;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\sprintf;
use function Safe\substr;
use function Safe\unlink;
use function str_starts_with;
use function sys_get_temp_dir;
use function trim;

final class RenderPlantUmlDiagramInline implements PageFormatter
{
    private const PLANTUML_JAR = __DIR__ . '/../../bin/plantuml.jar';

    /** @throws RuntimeException */
    public function __invoke(DocbookPage $page): DocbookPage
    {
        return $page->withReplacedContent(
            preg_replace_callback(
                '/```puml([\w\W]*?)```/',
                static function (array $m) use ($page) {
                    /** @var array{1: string} $m */
                    if (! str_starts_with(trim($m[1]), '@startuml')) {
                        throw new RuntimeException(sprintf(
                            'Ensure the PUML in %s starts with @startuml and ends with @enduml',
                            $page->slug()
                        ));
                    }

                    $contentHash  = md5($m[1]);
                    $pumlFilename = sys_get_temp_dir() . '/' . $contentHash . '.puml';
                    $pngFilename  = sys_get_temp_dir() . '/' . $contentHash . '.png';
                    file_put_contents($pumlFilename, $m[1]);
                    /** @psalm-suppress ForbiddenCode */
                    exec(
                        escapeshellcmd('java -jar ' . self::PLANTUML_JAR . ' ' . $pumlFilename) . ' 2>&1',
                        $output,
                        $exitCode
                    );

                    if ($exitCode !== 0) {
                        /** @psalm-var list<string> $output */
                        throw new RuntimeException(sprintf(
                            'Failed to render PUML in %s - starts "%s". Output was: %s',
                            $page->slug(),
                            substr($m[1], 0, 15),
                            implode("\n", $output)
                        ));
                    }

                    $pngContent = base64_encode(file_get_contents($pngFilename));
                    unlink($pumlFilename);
                    unlink($pngFilename);

                    return '![Diagram](data:image/png;base64,' . $pngContent . ')';
                },
                $page->content()
            )
        );
    }
}
