<?php

declare(strict_types=1);

namespace Roave\DocbookTool\Formatter;

use Psl\Encoding;
use Psl\Regex;
use Psl\Shell\ErrorOutputBehavior;
use Psl\Str;
use Psr\Log\LoggerInterface;
use Roave\DocbookTool\DocbookPage;
use RuntimeException;

use function md5;
use function Psl\Env\temp_dir;
use function Psl\File\read;
use function Psl\File\write;
use function Psl\Filesystem\canonicalize;
use function Psl\Filesystem\delete_file;
use function Psl\invariant;
use function Psl\Shell\execute;
use function sprintf;

final class RenderPlantUmlDiagramInline implements PageFormatter
{
    /** Note: this is added by the `Dockerfile` build, it no longer exists in the repo itself */
    private const string PLANTUML_JAR = __DIR__ . '/../../bin/plantuml.jar';

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /** @throws RuntimeException */
    public function __invoke(DocbookPage $page): DocbookPage
    {
        $this->logger->debug(sprintf('[%s] Checking if PlantUML diagrams can be rendered and inlined in %s', self::class, $page->slug()));

        return $page->withReplacedContent(
            Regex\replace_with(
                $page->content(),
                '/```puml([\w\W]*?)```/',
                function (array $m) use ($page) {
                    /** @var array{1: string} $m */
                    $match = $m[1];

                    $this->logger->debug(sprintf('[%s] Found PlantUML diagram to render in %s', self::class, $page->slug()));

                    $umlRegex = '/^(\s*@startuml)(.*)$/m';

                    invariant(
                        Regex\matches($match, $umlRegex),
                        sprintf(
                            'Ensure the PUML in %s starts with @startuml and ends with @enduml',
                            $page->slug(),
                        ),
                    );

                    // fix any "@startuml filename" first lines to omit the filename
                    $match = Regex\replace($match, '/^(\s*@startuml)(.*)$/m', '\\1');

                    $temporaryDir = temp_dir();
                    $contentHash  = md5($match);
                    $pumlFilename = $temporaryDir . '/' . $contentHash . '.puml';
                    $pngFilename  = $temporaryDir . '/' . $contentHash . '.png';
                    write($pumlFilename, $match);

                    $this->logger->debug(sprintf(
                        '[%s] Using %s to render a PlantUML diagram in %s...',
                        self::class,
                        (string) canonicalize(self::PLANTUML_JAR),
                        $page->slug(),
                    ));

                    try {
                        /** @psalm-suppress ForbiddenCode */
                        execute(
                            'java',
                            ['-jar', self::PLANTUML_JAR, '-v', $pumlFilename],
                            error_output_behavior: ErrorOutputBehavior::Append,
                        );
                    } catch (\Psl\Shell\Exception\RuntimeException $exception) {
                        throw new RuntimeException(
                            sprintf(
                                'Failed to render PUML in %s - starts "%s".',
                                $page->slug(),
                                Str\slice($match, 0, 30),
                            ),
                            previous: $exception,
                        );
                    }

                    $this->logger->debug(sprintf('[%s] PlantUML diagram render complete %s', self::class, $page->slug()));

                    $pngContent = Encoding\Base64\encode(read($pngFilename));
                    delete_file($pumlFilename);
                    delete_file($pngFilename);

                    return '![Diagram](data:image/png;base64,' . $pngContent . ')';
                },
            ),
        );
    }
}
