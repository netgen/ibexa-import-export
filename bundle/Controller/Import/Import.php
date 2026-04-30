<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller\Import;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Netgen\IbexaImportExportBundle\Form\ImportType;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_values;
use function date;
use function file_put_contents;
use function is_array;
use function is_dir;
use function mkdir;
use function preg_match;
use function preg_replace;
use function sprintf;
use function uniqid;

final class Import extends AbstractController
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
                $this->translator->trans('netgen.ibexa_import_export.error.php', [], 'import_export'),
            );
        }

        if (!is_dir('../' . $this->migrationsPath)) {
            mkdir('../' . $this->migrationsPath, 0777, true);
        }

        $form = $this->createForm(ImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile */
            $uploadedFile = $form->get('package')->getData();

            try {
                $yamlParsed = Yaml::parseFile($uploadedFile->getRealPath());
            } catch (ParseException $e) {
                $this->logger->error('Import YAML parse error: ' . $e->getMessage());
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'netgen.ibexa_import_export.error.import.invalid_file',
                        [],
                        'import_export',
                    ),
                );

                return $this->render(
                    '@NetgenIbexaImportExport/import.html.twig',
                    ['form' => $form->createView()],
                );
            }

            if (!is_array($yamlParsed) || $yamlParsed === [] || !isset($yamlParsed[0]['mode'])) {
                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'netgen.ibexa_import_export.error.import.invalid_file',
                        [],
                        'import_export',
                    ),
                );

                return $this->render(
                    '@NetgenIbexaImportExport/import.html.twig',
                    ['form' => $form->createView()],
                );
            }

            $importMode = $yamlParsed[0]['mode'];

            if ($importMode === 'create') {
                $parentLocationId = $form->get('parent_location')->getData();

                try {
                    $parentLocation = $this->repository->sudo(
                        fn () => $this->locationService->loadLocation((int) $parentLocationId),
                    );
                } catch (NotFoundException) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            'netgen.ibexa_import_export.error.import.parent_location',
                            [],
                            'import_export',
                        ),
                    );

                    return $this->render(
                        '@NetgenIbexaImportExport/import.html.twig',
                        [
                            'form' => $form->createView(),
                        ],
                    );
                }
                $yamlParsed[0]['parent_location'] = $parentLocation->remoteId;
                foreach ($yamlParsed as $key => $content) {
                    $contentRemoteId = $content['remote_id'] ?? null;
                    $locationRemoteId = $content['location_remote_id'] ?? null;

                    if ($contentRemoteId === null || $locationRemoteId === null) {
                        continue;
                    }

                    try {
                        $this->repository->sudo(fn () => $this->contentService->loadContentByRemoteId($contentRemoteId));
                        $this->repository->sudo(fn () => $this->locationService->loadLocationByRemoteId($locationRemoteId));
                        unset($yamlParsed[$key]);
                    } catch (NotFoundException) {
                        // Do nothing
                    }
                }
                // Reindex so the dumped YAML is a sequence (kaliop expects a list of steps,
                // not an associative map keyed by surviving indexes).
                $yamlParsed = array_values($yamlParsed);
            } else {
                foreach ($yamlParsed as $key => $content) {
                    $contentRemoteId = $content['match']['content_remote_id'] ?? null;

                    if ($contentRemoteId === null) {
                        continue;
                    }

                    try {
                        $this->repository->sudo(fn () => $this->contentService->loadContentByRemoteId($contentRemoteId));
                    } catch (NotFoundException) {
                        unset($yamlParsed[$key]);
                    }
                }
                $yamlParsed = array_values($yamlParsed);
            }

            $yaml = Yaml::dump($yamlParsed);

            $projectRoot = $this->container->getParameter('kernel.project_dir');
            $randomTimeComponent = date('YmdHis');
            // Server-generate the filename: never trust getClientOriginalName(), which a malicious
            // upload could craft to traverse out of the migrations directory.
            $safeOriginalName = preg_replace('/[^A-Za-z0-9._-]/', '_', $uploadedFile->getClientOriginalName());
            $newFilePath = $projectRoot . '/' . $this->migrationsPath . '/' . $randomTimeComponent . '_' . uniqid() . '_' . $safeOriginalName;

            file_put_contents($newFilePath, $yaml);

            $process = new Process(
                [
                    $phpPath,
                    '../bin/console',
                    'kaliop:migration:migrate',
                    '--path=' . $newFilePath,
                ],
            );
            $additionalAnswers = "Y\n";
            $process->setInput($additionalAnswers);
            $process->run();

            $output = $process->getOutput();
            $hasFailedMigrations = preg_match('/failed\s+([1-9]\d*)/i', $output) === 1;
            $importFailed = !$process->isSuccessful() || $hasFailedMigrations;

            if ($importFailed) {
                $error = sprintf(
                    'The command "%s" failed. Exit Code: %s(%s) Working directory: %s',
                    $process->getCommandLine(),
                    $process->getExitCode(),
                    $process->getExitCodeText(),
                    $process->getWorkingDirectory(),
                );

                $this->logger->error($error);
                $this->logger->error($process->getErrorOutput());
                $this->logger->error($output);

                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'netgen.ibexa_import_export.error.import',
                        [],
                        'import_export',
                    ),
                );
            } else {
                $this->addFlash(
                    'success',
                    $this->translator->trans(
                        'netgen.ibexa_import_export.success.import',
                        [],
                        'import_export',
                    ),
                );

                $this->logger->info('Import successful: ' . $output);
            }

            return $this->redirectToRoute('netgen_import_export.route.admin.import');
        }

        return $this->render(
            '@NetgenIbexaImportExport/import.html.twig',
            [
                'form' => $form->createView(),
            ],
        );
    }
}
