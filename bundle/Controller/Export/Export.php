<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller\Export;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
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

use function array_filter;
use function array_unique;
use function array_values;
use function basename;
use function count;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;
use function str_replace;
use function usort;

final class Export extends AbstractController
{
    public function __construct(
        private readonly Repository $repository,
        private readonly ContentService $contentService,
        private readonly LocationService $locationService,
        private readonly string $migrationsPath,
        private readonly ?string $phpBinaryPath,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ibexa:import_export:access');

        $phpPath = $this->phpBinaryPath;

        if ($phpPath === null || $phpPath === '') {
            $phpFinder = new PhpExecutableFinder();
            $phpPath = $phpFinder->find();
        }

        if ($phpPath === false) {
            throw new RuntimeException(
                $this->translator->trans(
                    'netgen.ibexa_import_export.error.php',
                    [],
                    'import_export',
                ),
            );
        }

        if (!is_dir('../' . $this->migrationsPath)) {
            mkdir('../' . $this->migrationsPath, 0777, true);
        }

        $form = $this->createForm(ExportType::class);
        $form->handleRequest($request);
        $fileNames = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $contentIds = (array) $form->get('source')->getData();
            $contentIds = array_values(array_unique(array_filter($contentIds, static fn ($id): bool => $id !== null && $id !== '')));
            $migrationType = $form->get('migration_type')->getData();
            $sourceStructure = $form->get('source_structure')->getData();

            foreach ($contentIds as $contentId) {
                $generated = $this->exportSingleContent(
                    (int) $contentId,
                    (string) $migrationType,
                    (string) $sourceStructure,
                    $phpPath,
                );

                if ($generated !== null) {
                    $fileNames[] = $generated;
                }
            }

            if (count($fileNames) > 0) {
                $this->addFlash(
                    'success',
                    $this->translator->trans(
                        'netgen.ibexa_import_export.success.export',
                        [],
                        'import_export',
                    ),
                );
            }
        }

        return $this->render(
            '@NetgenIbexaImportExport/export.html.twig',
            [
                'form' => $form->createView(),
                'files' => $fileNames,
            ],
        );
    }

    /**
     * Generates the migration YAML for a single content (or its subtree) and post-processes it.
     *
     * Returns the generated file name on success, or null on any failure (a flash message is set).
     */
    private function exportSingleContent(
        int $contentId,
        string $migrationType,
        string $sourceStructure,
        string $phpPath,
    ): ?string {
        try {
            $content = $this->repository->sudo(
                fn () => $this->contentService->loadContent($contentId),
            );
        } catch (NotFoundException) {
            $this->addFlash(
                'error',
                $this->translator->trans(
                    'netgen.ibexa_import_export.error.export.content',
                    [],
                    'import_export',
                ),
            );

            return null;
        }

        // Suffix the migration name with the content id so concurrent generations
        // within the same second do not collide on file name.
        $migrationName = $sourceStructure === 'subtree'
            ? $migrationType . '_subtree_' . $contentId
            : $migrationType . '_content_' . $contentId;

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
                    '--lang=all',
                    '../' . $this->migrationsPath,
                    $migrationName,
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
                    '--lang=all',
                    '../' . $this->migrationsPath,
                    $migrationName,
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
            $this->logger->error($process->getErrorOutput());

            $this->addFlash(
                'error',
                $this->translator->trans(
                    'netgen.ibexa_import_export.error.export',
                    [],
                    'import_export',
                ),
            );

            return null;
        }

        $fileName = str_replace("\n", '', basename($process->getOutput()));
        $projectRoot = $this->container->getParameter('kernel.project_dir');
        $filePath = $projectRoot . '/' . $this->migrationsPath . '/' . $fileName;
        $yamlParsed = Yaml::parseFile($filePath);

        foreach ($yamlParsed as &$content) {
            if ($migrationType === 'create') {
                $location = $this->repository->sudo(
                    fn () => $this->locationService->loadLocation($content['parent_location']),
                );
                $locationRemoteId = $location->remoteId;
                $content['parent_location'] = $locationRemoteId;
                $content['exported_content_name'] = $this->repository->sudo(
                    fn () => $this->contentService->loadContentByRemoteId($content['remote_id'])->getName(),
                );
                // parent depth + 1 = depth of this content's location
                $content['__sort_depth'] = $location->depth + 1;
            } elseif ($migrationType === 'update') {
                $remoteId = $content['new_remote_id'] ?? $content['match']['content_remote_id'] ?? '';

                $loadedContent = $this->repository->sudo(
                    fn () => $this->contentService->loadContentByRemoteId($remoteId),
                );
                $content['exported_content_name'] = $loadedContent->getName();

                $mainLocation = $this->repository->sudo(
                    fn () => $this->locationService->loadLocation($loadedContent->contentInfo->mainLocationId),
                );
                $content['__sort_depth'] = $mainLocation->depth;
            }
        }
        unset($content);

        // Sort entries so parents (shallower depth) come before children. Required for create-mode
        // imports where a child cannot be created before its parent location exists.
        usort(
            $yamlParsed,
            static fn (array $a, array $b): int => ($a['__sort_depth'] ?? 0) <=> ($b['__sort_depth'] ?? 0),
        );

        foreach ($yamlParsed as &$content) {
            unset($content['__sort_depth']);
        }
        unset($content);

        $yaml = Yaml::dump($yamlParsed);
        file_put_contents($filePath, $yaml);

        $this->logger->info('Export successful: ' . $process->getOutput());

        return $fileName;
    }
}
