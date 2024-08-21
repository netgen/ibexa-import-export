<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller\Export;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Netgen\IbexaImportExportBundle\Form\ExportType;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

use function basename;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;
use function str_replace;

final class Export extends AbstractController
{
    private const TRANSLATION_DOMAIN = 'import_export';

    public function __construct(
        private readonly ContentService $contentService,
        private readonly LocationService $locationService,
        private readonly string $migrationsPath,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(Request $request): Response
    {
        $phpFinder = new PhpExecutableFinder();

        $phpPath = $phpFinder->find();

        if ($phpPath === false) {
            throw new RuntimeException(
                $this->translator->trans(
                    'netgen.ibexa_import_export.error.php',
                    [],
                    $this::TRANSLATION_DOMAIN,
                ),
            );
        }

        if (!is_dir('../' . $this->migrationsPath)) {
            mkdir('../' . $this->migrationsPath, 0777, true);
        }

        $form = $this->createForm(ExportType::class);
        $form->handleRequest($request);
        $fileName = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $contentId = $form->get('source')->getData();
            $migrationType = $form->get('migration_type')->getData();
            $sourceStructure = $form->get('source_structure')->getData();

            try {
                $content = $this->contentService->loadContent((int) $contentId);
            } catch (NotFoundException) {
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'netgen.ibexa_import_export.error.export.content',
                        [],
                        $this::TRANSLATION_DOMAIN,
                    ),
                );

                return $this->render(
                    '@NetgenIbexaImportExport/export.html.twig',
                    [
                        'form' => $form->createView(),
                        'file' => $fileName,
                    ],
                );
            }

            if ($sourceStructure === 'subtree') {
                $subtreePath = $content->contentInfo->getMainLocation()->pathString;
                $process = new Process(
                    [
                        $phpPath,
                        '../bin/console',
                        'kaliop:migration:generate',
                        '--type=content',
                        '--match-type=subtree',
                        '--match-value=' . $subtreePath,
                        '--mode=' . $migrationType,
                        '../' . $this->migrationsPath,
                        $migrationType . '_subtree',
                    ],
                );
            } else {
                $process = new Process(
                    [
                        $phpPath,
                        '../bin/console',
                        'kaliop:migration:generate',
                        '--type=content',
                        '--match-type=content_id',
                        '--match-value=' . $contentId,
                        '--mode=' . $migrationType,
                        '../' . $this->migrationsPath,
                        $migrationType . '_content',
                    ],
                );
            }

            $process->run();

            if (!$process->isSuccessful()) {
                $error = sprintf(
                    'The command "%s" failed. Exit Code: %s(%s) Working directory: %s',
                    $process->getCommandLine(),
                    $process->getExitCode(),
                    $process->getExitCodeText(),
                    $process->getWorkingDirectory(),
                );

                $this->logger->error($error);

                $this->addFlash(
                    'error',
                    $error,
                );
            } else {
                $fileName = str_replace("\n", '', basename($process->getOutput()));
                $projectRoot = $this->container->getParameter('kernel.project_dir');
                $filePath = $projectRoot . '/' . $this->migrationsPath . '/' . $fileName;
                $yamlParsed = Yaml::parseFile($filePath);

                /*
                 * @phpstan-ignore-next-line
                 */
                foreach ($yamlParsed as &$content) {
                    if ($migrationType === 'create') {
                        $location = $this->locationService->loadLocation($content['parent_location']);
                        $locationRemoteId = $location->remoteId;
                        $content['parent_location'] = $locationRemoteId;
                    }
                }

                $yaml = Yaml::dump($yamlParsed);
                file_put_contents($filePath, $yaml);

                $this->addFlash(
                    'success',
                    $this->translator->trans(
                        'netgen.ibexa_import_export.success.export',
                        [],
                        $this::TRANSLATION_DOMAIN,
                    ),
                );
            }
        }

        return $this->render(
            '@NetgenIbexaImportExport/export.html.twig',
            [
                'form' => $form->createView(),
                'file' => $fileName ?? null,
            ],
        );
    }
}
