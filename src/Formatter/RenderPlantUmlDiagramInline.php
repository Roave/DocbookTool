<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Roave\DocbookTool\DocbookPage;
use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\unlink;

final class RenderPlantUmlDiagramInline implements PageFormatter
{

    public function __invoke(DocbookPage $page) : DocbookPage
    {
        return $page->withReplacedContent(
            preg_replace_callback(
                '/```puml([\w\W]*?)```/',
                /** @psalm-param array<int,string> $m */
                static function ($m) {
                    $contentHash  = md5($m[1]);
                    $pumlFilename = sys_get_temp_dir() . '/' . $contentHash . '.puml';
                    $pngFilename  = sys_get_temp_dir() . '/' . $contentHash . '.png';
                    file_put_contents($pumlFilename, $m[1]);
                    /** @noinspection UnusedFunctionResultInspection */
                    /** @psalm-suppress ForbiddenCode */
                    shell_exec(escapeshellcmd('java -jar plantuml.jar ' . $pumlFilename));
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
